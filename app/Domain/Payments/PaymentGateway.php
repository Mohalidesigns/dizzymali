<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * The contract every payment provider implements.
 *
 * Paystack is the primary. Flutterwave is the international fallback and is
 * fully written but inert until its credentials land. A third provider — or a
 * replacement for either — only has to satisfy these five methods.
 */
interface PaymentGateway
{
    public function name(): string;

    /** Whether real credentials exist. False means every call below will throw. */
    public function isConfigured(): bool;

    /** Which currencies this gateway can actually charge in. */
    public function supports(string $currency): bool;

    /** @throws GatewayNotConfigured|PaymentFailed */
    public function initialise(ChargeIntent $intent): ChargeSession;

    /** @throws GatewayNotConfigured */
    public function verify(string $gatewayReference): VerificationResult;

    /**
     * Validate the signature on an incoming webhook and parse it.
     *
     * Returns null when the signature does not match — which must be treated as
     * a hostile request, not a malformed one.
     */
    /** @param  array<string,mixed>  $headers */
    public function parseWebhook(string $payload, array $headers): ?WebhookEvent;
}
