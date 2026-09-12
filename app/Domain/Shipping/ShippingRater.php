<?php

declare(strict_types=1);

namespace App\Domain\Shipping;

use App\Models\ShippingRate;
use App\Models\ShippingZone;
use App\Support\Money;

final readonly class ShippingQuote
{
    public function __construct(
        public Money $price,
        public ?ShippingZone $zone = null,
        public ?ShippingRate $rate = null,
        public ?int $transitDaysMin = null,
        public ?int $transitDaysMax = null,
        public bool $requiresManualQuote = false,
    ) {}
}

class ShippingRater
{
    public function quote(
        ?string $countryCode,
        int $weightGrams,
        string $serviceLevel = 'standard',
    ): ShippingQuote {
        $zone = ShippingZone::forCountry($countryCode);

        if ($zone === null) {
            return new ShippingQuote(Money::zero(), requiresManualQuote: true);
        }

        $rate = $zone->rates()
            ->where('is_active', true)
            ->where('service_level', $serviceLevel)
            ->where('min_grams', '<=', $weightGrams)
            ->where('max_grams', '>=', $weightGrams)
            ->orderBy('price_kobo')
            ->first();

        // Over the heaviest band we still have to charge something rather than
        // shipping for free, so fall back to the top band for the zone.
        $rate ??= $zone->rates()
            ->where('is_active', true)
            ->where('service_level', $serviceLevel)
            ->orderByDesc('max_grams')
            ->first();

        if ($rate === null) {
            return new ShippingQuote(Money::zero(), $zone, requiresManualQuote: true);
        }

        return new ShippingQuote(
            price: $rate->price(),
            zone: $zone,
            rate: $rate,
            transitDaysMin: (int) $rate->transit_days_min,
            transitDaysMax: (int) $rate->transit_days_max,
        );
    }
}
