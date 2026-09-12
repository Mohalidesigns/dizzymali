<?php

declare(strict_types=1);

namespace App\Domain\Currency;

use App\Models\Currency;
use App\Support\Money;

final readonly class DisplayRate
{
    public function __construct(
        public string $code,
        public string $symbol,
        public int $decimals,
        public int $roundingMinor,
        public int $rate1e8,
        public int $marginBasisPoints,
    ) {}
}

/**
 * Converts the NGN kobo total into the currency the customer is shopping in.
 *
 * The rate is only ever read here and then FROZEN onto the order at checkout.
 * A customer quoted £180 pays £180 even if the naira moves overnight.
 */
class CurrencyConverter
{
    public function displayRateFor(string $code): ?DisplayRate
    {
        $code = strtoupper($code);

        if ($code === 'NGN') {
            return null;
        }

        $currency = Currency::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->with('currentRate')
            ->first();

        if ($currency === null || $currency->currentRate === null) {
            return null;
        }

        return new DisplayRate(
            code: $code,
            symbol: (string) $currency->symbol,
            decimals: (int) $currency->decimals,
            roundingMinor: (int) $currency->rounding_minor,
            rate1e8: $currency->currentRate->rate1e8(),
            marginBasisPoints: $currency->currentRate->marginBasisPoints(),
        );
    }

    public function convert(Money $ngn, DisplayRate $rate): Money
    {
        return $ngn
            ->convertTo($rate->code, $rate->rate1e8, $rate->decimals)
            ->plusBasisPoints($rate->marginBasisPoints)
            ->roundUpToNearest($rate->roundingMinor);
    }
}
