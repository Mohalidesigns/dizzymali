<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Currency\CurrencyConverter;
use App\Domain\Pricing\QuoteContext;
use App\Domain\Shipping\ShippingRater;
use App\Models\Order;
use App\Models\Setting;
use App\Support\Money;

/**
 * Assembles the order-level pricing inputs from server-side sources only.
 *
 * Nothing on the returned context comes from the request body. That is the
 * whole point: the client tells us WHICH fabric and WHERE to ship, never what
 * any of it costs.
 */
class BuildQuoteContext
{
    public function __construct(
        private readonly ShippingRater $shipping,
        private readonly CurrencyConverter $currency,
    ) {}

    public function handle(Order $order, int $weightGrams, bool $useFrozenFx = false): QuoteContext
    {
        $countryCode = $this->destinationCountry($order);

        $shippingQuote = $weightGrams > 0
            ? $this->shipping->quote($countryCode, $weightGrams, (string) $order->service_level)
            : null;

        $shippingCost = $shippingQuote === null ? Money::zero() : $shippingQuote->price;

        $displayCurrency = strtoupper((string) ($order->currency_code ?: 'NGN'));

        // A submitted order keeps the rate it was quoted at, always.
        if ($useFrozenFx && $order->fx_rate_used !== null) {
            return new QuoteContext(
                shipping: $shippingCost,
                discount: Money::ofMinor((int) $order->discount_kobo),
                tax: Money::ofMinor((int) $order->tax_kobo),
                isExpress: $order->service_level === 'express',
                expressSurchargeBasisPoints: $this->expressSurchargeBasisPoints(),
                displayCurrency: $displayCurrency,
                fxRate1e8: (int) round(((float) $order->fx_rate_used) * 100_000_000),
                fxMarginBasisPoints: (int) round(((float) $order->fx_margin_percent) * 100),
            );
        }

        $rate = $this->currency->displayRateFor($displayCurrency);

        return new QuoteContext(
            shipping: $shippingCost,
            discount: Money::ofMinor((int) $order->discount_kobo),
            tax: Money::ofMinor((int) $order->tax_kobo),
            isExpress: $order->service_level === 'express',
            expressSurchargeBasisPoints: $this->expressSurchargeBasisPoints(),
            displayCurrency: $rate === null ? 'NGN' : $rate->code,
            fxRate1e8: $rate === null ? null : $rate->rate1e8,
            fxMarginBasisPoints: $rate === null ? 0 : $rate->marginBasisPoints,
            displayRoundingMinor: $rate === null ? 1 : $rate->roundingMinor,
            displayDecimals: $rate === null ? 2 : $rate->decimals,
        );
    }

    /** Where we are shipping to, preferring the frozen snapshot over live data. */
    private function destinationCountry(Order $order): ?string
    {
        $snapshot = $order->shipping_address_snapshot;

        if (is_array($snapshot) && isset($snapshot['country_code'])) {
            return (string) $snapshot['country_code'];
        }

        $address = $order->shippingAddress;

        if ($address !== null) {
            return $address->country_code;
        }

        return $order->user?->country_code;
    }

    private function expressSurchargeBasisPoints(): int
    {
        return (int) Setting::get('pricing.express_surcharge_bp', 2_500); // 25%
    }
}
