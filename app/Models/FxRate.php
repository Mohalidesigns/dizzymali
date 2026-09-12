<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $currency_id
 * @property string $rate
 * @property string $margin_percent
 * @property string $source
 * @property \Illuminate\Support\Carbon|null $effective_at
 */
class FxRate extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'margin_percent' => 'decimal:2',
            'effective_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /** The rate as 1e8 fixed point, which is what Money::convertTo expects. */
    public function rate1e8(): int
    {
        return (int) round(((float) $this->rate) * 100_000_000);
    }

    public function marginBasisPoints(): int
    {
        return (int) round(((float) $this->margin_percent) * 100);
    }
}
