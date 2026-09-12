<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $symbol
 * @property int $decimals
 * @property int $rounding_minor
 * @property bool $is_active
 * @property-read FxRate|null $currentRate
 */
class Currency extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'decimals' => 'integer',
            'rounding_minor' => 'integer',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<FxRate, $this> */
    public function rates(): HasMany
    {
        return $this->hasMany(FxRate::class);
    }

    /** @return HasOne<FxRate, $this> */
    public function currentRate(): HasOne
    {
        return $this->hasOne(FxRate::class)->latestOfMany('effective_at');
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Currency>  $query */
    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
