<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property list<string>|null $country_codes
 * @property bool $is_default
 * @property bool $is_active
 */
class ShippingZone extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'country_codes' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<ShippingRate, $this> */
    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }

    public static function forCountry(?string $countryCode): ?self
    {
        $zones = self::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($countryCode !== null) {
            foreach ($zones as $zone) {
                if (in_array(strtoupper($countryCode), $zone->country_codes ?? [], true)) {
                    return $zone;
                }
            }
        }

        return $zones->firstWhere('is_default', true);
    }
}
