<?php

declare(strict_types=1);

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\ChargeIntent;
use App\Domain\Payments\ChargeSession;
use App\Domain\Payments\GatewayNotConfigured;
use App\Domain\Payments\PaymentFailed;
use App\Domain\Payments\PaymentGateway;
use App\Domain\Payments\VerificationResult;
use App\Domain\Payments\WebhookEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Paystack — the primary gateway, and the one Nigerian cards work best on.
 *
 * Card data never touches this server. We initialise a transaction, send the
 * customer to Paystack's own checkout, and trust nothing that comes back until
 * we have verified it against Paystack's API ourselves.
 */
class PaystackGateway implements PaymentGateway
{
    public function __construct(
        private readonly ?string $secretKey,
        private readonly ?string $publicKey,
        private readonly string $baseUrl = 'https://api.paystack.co',
        private readonly int $timeoutSeconds = 20,
    ) {}

    public function name(): string
    {
        return 'paystack';
    }

    public function isConfigured(): bool
    {
        return filled($this->secretKey) && filled($this->publicKey);
    }

    public function supports(string $currency): bool
    {
        return in_array(strtoupper($currency), ['NGN', 'GHS', 'ZAR', 'KES', 'USD'], true);
    }

    public function initialise(ChargeIntent $intent): ChargeSession
    {
        $this->assertConfigured();

        $response = $this->client()->post('/transaction/initialize', [
            'email' => $intent->customerEmail,
            'amount' => $intent->displayAmountMinor,
            'currency' => strtoupper($intent->displayCurrency),
            'reference' => $this->buildReference($intent->reference),
            'callback_url' => $intent->callbackUrl,
            'metadata' => [
                'order_reference' => $intent->reference,
                'customer_name' => $intent->customerName,
                ...$intent->metadata,
            ],
        ]);

        if ($response->failed() || $response->json('status') !== true) {
            throw new PaymentFailed(
                'Paystack refused to start this transaction: '
                .($response->json('message') ?? 'no reason given.'),
            );
        }

        return new ChargeSession(
            gateway: $this->name(),
            gatewayReference: (string) $response->json('data.reference'),
            redirectUrl: (string) $response->json('data.authorization_url'),
            raw: (array) $response->json('data'),
        );
    }

    public function verify(string $gatewayReference): VerificationResult
    {
        $this->assertConfigured();

        $response = $this->client()->get('/transaction/verify/'.urlencode($gatewayReference));

        if ($response->failed() || $response->json('status') !== true) {
            return VerificationResult::failed(
                $gatewayReference,
                (string) ($response->json('message') ?? 'Paystack could not verify this reference.'),
            );
        }

        $data = (array) $response->json('data');

        return new VerificationResult(
            successful: ($data['status'] ?? null) === 'success',
            gatewayReference: (string) ($data['reference'] ?? $gatewayReference),
            amountMinor: (int) ($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? 'NGN')),
            paidAt: $data['paid_at'] ?? null,
            failureReason: ($data['status'] ?? null) === 'success' ? null : ($data['gateway_response'] ?? null),
            raw: $data,
        );
    }

    /** @param  array<string,mixed>  $headers */
    public function parseWebhook(string $payload, array $headers): ?WebhookEvent
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $signature = $this->header($headers, 'x-paystack-signature');

        if ($signature === null) {
            return null;
        }

        $expected = hash_hmac('sha512', $payload, (string) $this->secretKey);

        // Constant-time comparison. A timing-safe check costs nothing and an
        // unsafe one is a genuine, demonstrated attack against HMAC webhooks.
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        /** @var array<string,mixed> $decoded */
        $decoded = json_decode($payload, true) ?: [];
        $data = (array) ($decoded['data'] ?? []);

        return new WebhookEvent(
            gateway: $this->name(),
            type: (string) ($decoded['event'] ?? 'unknown'),
            gatewayReference: isset($data['reference']) ? (string) $data['reference'] : null,
            amountMinor: isset($data['amount']) ? (int) $data['amount'] : null,
            currency: isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
            raw: $decoded,
        );
    }

    private function buildReference(string $orderReference): string
    {
        // Unique per attempt: a customer who abandons checkout and comes back
        // must not collide with their own earlier, still-open transaction.
        return strtolower($orderReference).'-'.Str::lower(Str::random(8));
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken((string) $this->secretKey)
            ->acceptJson()
            ->timeout($this->timeoutSeconds)
            ->retry(2, 250, throw: false);
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw GatewayNotConfigured::for($this->name());
        }
    }

    /** @param  array<string,mixed>  $headers */
    private function header(array $headers, string $name): ?string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }

        return null;
    }
}
