<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\ChargeIntent;
use App\Domain\Payments\ChargeSession;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\VerificationResult;
use App\Domain\Payments\WebhookEvent;
use Illuminate\Support\Str;

/**
 * Bank transfer, settled by hand.
 *
 * A meaningful share of Nigerian business still moves by transfer, and a
 * customer who would rather not put a card into a website is a customer, not a
 * problem. This records the intent; a staff member marks it paid from the admin
 * once the money lands, which writes a payment row with a proper audit trail
 * rather than somebody editing a total.
 */
class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function supports(string $currency): bool
    {
        return true;
    }

    public function initialise(ChargeIntent $intent): ChargeSession
    {
        return new ChargeSession(
            gateway: $this->name(),
            gatewayReference: strtolower($intent->reference).'-'.Str::lower(Str::random(8)),
            redirectUrl: $intent->callbackUrl,
            raw: ['instructions' => 'Awaiting bank transfer. Staff will confirm receipt.'],
        );
    }

    /** Never automatically successful. A human confirms, or nobody does. */
    public function verify(string $gatewayReference): VerificationResult
    {
        return VerificationResult::failed(
            $gatewayReference,
            'Bank transfers are confirmed by staff, not automatically.',
        );
    }

    /** @param  array<string,mixed>  $headers */
    public function parseWebhook(string $payload, array $headers): ?WebhookEvent
    {
        return null;
    }
}
