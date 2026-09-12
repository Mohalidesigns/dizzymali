<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Support\Money;

/**
 * The order-level inputs to a quote. Everything here is server-derived; nothing
 * on this object may ever be populated straight from a client request body.
 */
final readonly class QuoteContext
{
    public function __construct(
        public Money $shipping,
        public Money $discount,
        public Money $tax,
        public bool $isExpress = false,
        public int $expressSurchargeBasisPoints = 0,
        public string $displayCurrency = 'NGN',
        public ?int $fxRate1e8 = null,
        public int $fxMarginBasisPoints = 0,
        public int $displayRoundingMinor = 1,
        public int $displayDecimals = 2,
    ) {}

    public static function plain(): self
    {
        return new self(Money::zero(), Money::zero(), Money::zero());
    }
}
