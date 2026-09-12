<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Support\Money;

/**
 * What we are asking a gateway to collect.
 *
 * The amount here is always server-calculated. Nothing on this object has ever
 * been near a request body.
 */
final readonly class ChargeIntent
{
    public function __construct(
        public string $reference,
        public Money $amount,
        public string $displayCurrency,
        public int $displayAmountMinor,
        public string $customerEmail,
        public string $customerName,
        public ?string $customerPhone,
        public string $callbackUrl,
        /** @var array<string,mixed> */
        public array $metadata = [],
    ) {}
}
