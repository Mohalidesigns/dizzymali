<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $shipping_zone_id
 * @property string $service_level
 * @property int $min_grams
 * @property int $max_grams
 * @property int $price_kobo
 * @property int $transit_days_min
 * @property int $transit_days_max
 */
class ShippingRate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'min_grams' => 'integer',
            'max_grams' => 'integer',
            'price_kobo' => 'integer',
            'transit_days_min' => 'integer',
            'transit_days_max' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ShippingZone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function price(): Money
    {
        return Money::ofMinor((int) $this->price_kobo);
    }
}
