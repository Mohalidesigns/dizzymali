<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\FxRate;
use App\Models\Setting;
use App\Models\ShippingRate;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * Currencies, FX, shipping zones and the pricing knobs.
 *
 * The FX rates here are placeholders. In production a scheduled job refreshes
 * them daily and the admin sets the margin that absorbs naira volatility.
 */
class CommerceSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            // code, name, symbol, decimals, rounding (minor units), rate per 1 NGN, margin %
            ['NGN', 'Nigerian Naira', '₦', 2, 100, null, null, true],
            ['USD', 'US Dollar', '$', 2, 100, 0.00065000, 8.00, false],
            ['GBP', 'Pound Sterling', '£', 2, 100, 0.00051200, 8.00, false],
            ['EUR', 'Euro', '€', 2, 100, 0.00060000, 8.00, false],
        ];

        foreach ($currencies as $i => [$code, $name, $symbol, $decimals, $rounding, $rate, $margin, $isBase]) {
            $currency = Currency::updateOrCreate(['code' => $code], [
                'name' => $name,
                'symbol' => $symbol,
                'decimals' => $decimals,
                'rounding_minor' => $rounding,
                'is_base' => $isBase,
                'is_active' => true,
                'sort_order' => $i,
            ]);

            if ($rate !== null) {
                FxRate::updateOrCreate(
                    ['currency_id' => $currency->id, 'effective_at' => today()],
                    ['rate' => $rate, 'margin_percent' => $margin, 'source' => 'seed'],
                );
            }
        }

        $zones = [
            ['nigeria-lagos', 'Nigeria — Lagos', ['NG'], false, [
                ['standard', 0, 2_000, 3_500_00, 1, 3],
                ['standard', 2_001, 6_000, 5_000_00, 1, 3],
                ['express', 0, 6_000, 9_000_00, 1, 1],
            ]],
            ['united-kingdom', 'United Kingdom', ['GB'], false, [
                ['standard', 0, 2_000, 28_000_00, 7, 12],
                ['standard', 2_001, 6_000, 42_000_00, 7, 12],
                ['express', 0, 6_000, 78_000_00, 3, 5],
            ]],
            ['europe', 'Europe', ['IE', 'FR', 'DE', 'NL', 'BE', 'ES', 'IT', 'PT', 'SE', 'DK', 'NO', 'CH', 'AT', 'PL'], false, [
                ['standard', 0, 2_000, 32_000_00, 8, 14],
                ['standard', 2_001, 6_000, 48_000_00, 8, 14],
                ['express', 0, 6_000, 86_000_00, 4, 6],
            ]],
            ['north-america', 'North America', ['US', 'CA'], false, [
                ['standard', 0, 2_000, 38_000_00, 9, 16],
                ['standard', 2_001, 6_000, 56_000_00, 9, 16],
                ['express', 0, 6_000, 98_000_00, 4, 7],
            ]],
            ['middle-east', 'Middle East', ['AE', 'SA', 'QA', 'KW', 'BH', 'OM'], false, [
                ['standard', 0, 6_000, 34_000_00, 6, 11],
                ['express', 0, 6_000, 72_000_00, 3, 5],
            ]],
            ['rest-of-world', 'Rest of world', [], true, [
                ['standard', 0, 6_000, 52_000_00, 12, 25],
                ['express', 0, 6_000, 110_000_00, 6, 10],
            ]],
        ];

        foreach ($zones as $i => [$slug, $name, $countries, $isDefault, $rates]) {
            $zone = ShippingZone::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'country_codes' => $countries,
                'is_default' => $isDefault,
                'is_active' => true,
                'sort_order' => $i,
            ]);

            $zone->rates()->delete();

            foreach ($rates as [$service, $min, $max, $price, $dMin, $dMax]) {
                ShippingRate::create([
                    'shipping_zone_id' => $zone->id,
                    'service_level' => $service,
                    'min_grams' => $min,
                    'max_grams' => $max,
                    'price_kobo' => $price,
                    'transit_days_min' => $dMin,
                    'transit_days_max' => $dMax,
                    'is_active' => true,
                ]);
            }
        }

        $settings = [
            ['pricing.express_surcharge_bp', 2_500, 'pricing'],       // 25% on sewing
            ['pricing.minimum_order_kobo', 25_000_00, 'pricing'],
            ['pricing.deposit_percent', 60, 'pricing'],
            ['pricing.allow_deposit', true, 'pricing'],
            ['orders.max_inspiration_images', 5, 'orders'],
            ['company.name', 'DizzyMali', 'company'],
            ['company.email', 'hello@dizzymali.com', 'company'],
            ['company.whatsapp', '+2348000000000', 'company'],
        ];

        foreach ($settings as [$key, $value, $group]) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }
    }
}
