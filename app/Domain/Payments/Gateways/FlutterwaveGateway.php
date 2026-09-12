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
 * Flutterwave — the international fallback, for the diaspora cards Paystack
 * declines.
 *
 * This is written in full against Flutterwave's v3 API. It is inert until
 * FLUTTERWAVE_SECRET_KEY and FLUTTERWAVE_SECRET_HASH are set: `isConfigured()`
 * returns false, the gateway manager skips it, and the admin payments screen
 * shows it as awaiting credentials. Nothing here needs rewriting when the keys
 * arrive — only the .env.
 */
class FlutterwaveGateway implements PaymentGateway
{
    public function __construct(
        private readonly ?string $secretKey,
        private readonly ?string $publicKey,
        private readonly ?string $secretHash,
        private readonly string $baseUrl = 'https://api.flutterwave.com/v3',
        private readonly int $timeoutSeconds = 20,
    ) {}

    public function name(): string
    {
        return 'flutterwave';
    }

    public function isConfigured(): bool
    {
        return filled($this->secretKey) && filled($this->publicKey) && filled($this->secretHash);
    }

    /** Flutterwave settles far more currencies than we display; these are ours. */
    public function supports(string $currency): bool
    {
        return in_array(strtoupper($currency), ['NGN', 'USD', 'GBP', 'EUR', 'KES', 'GHS', 'ZAR'], true);
    }

    public function initialise(ChargeIntent $intent): ChargeSession
    {
        $this->assertConfigured();

        $reference = strtolower($intent->reference).'-'.Str::lower(Str::random(8));

        $response = $this->client()->post('/payments', [
            'tx_ref' => $reference,
            // Flutterwave takes major units as a decimal string, not minor
            // units. We build that string from the integer so no float is
            // involved at any point.
            'amount' => $this->toMajorUnitString($intent->displayAmountMinor),
            'currency' => strtoupper($intent->displayCurrency),
            'redirect_url' => $intent->callbackUrl,
            'customer' => array_filter([
                'email' => $intent->customerEmail,
                'name' => $intent->customerName,
                'phonenumber' => $intent->customerPhone,
            ]),
            'customizations' => [
                'title' => 'DizzyMali',
                'description' => 'Bespoke garment '.$intent->reference,
            ],
            'meta' => [
                'order_reference' => $intent->reference,
                ...$intent->metadata,
            ],
        ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            throw new PaymentFailed(
                'Flutterwave refused to start this transaction: '
                .($response->json('message') ?? 'no reason given.'),
            );
        }

        return new ChargeSession(
            gateway: $this->name(),
            gatewayReference: $reference,
            redirectUrl: (string) $response->json('data.link'),
            raw: (array) $response->json('data'),
        );
    }

    public function verify(string $gatewayReference): VerificationResult
    {
        $this->assertConfigured();

        $response = $this->client()->get('/transactions/verify_by_reference', [
            'tx_ref' => $gatewayReference,
        ]);

        if ($response->failed() || $response->json('status') !== 'success') {
            return VerificationResult::failed(
                $gatewayReference,
                (string) ($response->json('message') ?? 'Flutterwave could not verify this reference.'),
            );
        }

        $data = (array) $response->json('data');

        return new VerificationResult(
            successful: ($data['status'] ?? null) === 'successful',
            gatewayReference: (string) ($data['tx_ref'] ?? $gatewayReference),
            amountMinor: $this->toMinorUnits($data['amount'] ?? 0),
            currency: strtoupper((string) ($data['currency'] ?? 'NGN')),
            paidAt: $data['created_at'] ?? null,
            failureReason: ($data['status'] ?? null) === 'successful' ? null : ($data['processor_response'] ?? null),
            raw: $data,
        );
    }

    /** @param  array<string,mixed>  $headers */
    public function parseWebhook(string $payload, array $headers): ?WebhookEvent
    {
        if (! $this->isConfigured()) {
            return null;
        }

        // Flutterwave sends a shared secret rather than an HMAC. It is still
        // compared in constant time.
        $given = $this->header($headers, 'verif-hash');

        if ($given === null || ! hash_equals((string) $this->secretHash, $given)) {
            return null;
        }

        /** @var array<string,mixed> $decoded */
        $decoded = json_decode($payload, true) ?: [];
        $data = (array) ($decoded['data'] ?? []);

        return new WebhookEvent(
            gateway: $this->name(),
            type: (string) ($decoded['event'] ?? 'unknown'),
            gatewayReference: isset($data['tx_ref']) ? (string) $data['tx_ref'] : null,
            amountMinor: isset($data['amount']) ? $this->toMinorUnits($data['amount']) : null,
            currency: isset($data['currency']) ? strtoupper((string) $data['currency']) : null,
            raw: $decoded,
        );
    }

    /** 15987 -> "159.87", built by string surgery so no float is involved. */
    private function toMajorUnitString(int $minor, int $decimals = 2): string
    {
        $negative = $minor < 0;
        $digits = str_pad((string) abs($minor), $decimals + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -$decimals);
        $fraction = substr($digits, -$decimals);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    /** "159.87" -> 15987, again without float arithmetic. */
    private function toMinorUnits(mixed $amount, int $decimals = 2): int
    {
        $value = trim((string) $amount);

        if ($value === '') {
            return 0;
        }

        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $fraction = substr(str_pad($fraction, $decimals, '0'), 0, $decimals);

        $minor = (int) ($whole.$fraction);

        return $negative ? -$minor : $minor;
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
