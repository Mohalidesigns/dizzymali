<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Domain\Payments\GatewayManager;
use App\Domain\Payments\WebhookEvent;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

/**
 * Receives a gateway webhook.
 *
 * The signature is verified first, by the gateway that claims to have sent it.
 * A request whose signature does not check out is discarded without further
 * processing and without a helpful error — it is a hostile request, and telling
 * it why it failed is free reconnaissance.
 */
class HandleGatewayWebhook
{
    public function __construct(
        private readonly GatewayManager $gateways,
        private readonly SettlePayment $settle,
    ) {}

    /**
     * @param  array<string,mixed>  $headers
     * @return bool True when the event was accepted, whether or not it settled anything.
     */
    public function handle(string $gatewayName, string $payload, array $headers): bool
    {
        if (! $this->gateways->has($gatewayName)) {
            return false;
        }

        $gateway = $this->gateways->get($gatewayName);
        $event = $gateway->parseWebhook($payload, $headers);

        if ($event === null) {
            Log::warning('Rejected a webhook with an invalid or missing signature.', [
                'gateway' => $gatewayName,
            ]);

            return false;
        }

        if (! $event->isSuccessfulCharge()) {
            // Refunds, chargebacks and transfer events are recorded but not
            // acted on yet. Better a logged no-op than a wrong guess.
            Log::info('Webhook received and ignored.', ['gateway' => $gatewayName, 'type' => $event->type]);

            return true;
        }

        $payment = $this->locatePayment($event);

        if ($payment === null) {
            Log::warning('Webhook referenced a payment we do not have.', [
                'gateway' => $gatewayName,
                'reference' => $event->gatewayReference,
            ]);

            return true;
        }

        // Re-verify against the gateway's API rather than trusting the webhook
        // body. The signature proves origin; only verification proves outcome.
        $this->settle->handle($payment, $gateway->verify((string) $event->gatewayReference));

        return true;
    }

    private function locatePayment(WebhookEvent $event): ?Payment
    {
        if ($event->gatewayReference === null) {
            return null;
        }

        return Payment::query()
            ->where('gateway', $event->gateway)
            ->where('gateway_reference', $event->gatewayReference)
            ->first();
    }
}
