<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * What the gateway says actually happened.
 *
 * `amountMinor` is in the currency the customer was charged in, which is not
 * necessarily naira. The caller checks it against what we asked for; a gateway
 * saying "successful" is not on its own a reason to mark an order paid.
 */
final readonly class VerificationResult
{
    public function __construct(
        public bool $successful,
        public string $gatewayReference,
        public int $amountMinor,
        public string $currency,
        public ?string $paidAt = null,
        public ?string $failureReason = null,
        /** @var array<string,mixed> */
        public array $raw = [],
    ) {}

    public static function failed(string $reference, string $reason): self
    {
        return new self(false, $reference, 0, 'NGN', failureReason: $reason);
    }
}
