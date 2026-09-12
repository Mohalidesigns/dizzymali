<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasMediaAssets;
use App\Domain\Pricing\FabricVariantSpec;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $fabric_id
 * @property string $sku
 * @property string $colour_name
 * @property string|null $colour_hex
 * @property string|null $pattern
 * @property int $price_per_yard_kobo
 * @property string $stock_yards
 * @property string $reserved_yards
 * @property string $low_stock_threshold_yards
 * @property string $min_order_yards
 * @property bool $is_active
 * @property-read Fabric|null $fabric
 */
class FabricVariant extends Model
{
    /** @use HasFactory<\Database\Factories\FabricVariantFactory> */
    use HasFactory, HasMediaAssets;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_per_yard_kobo' => 'integer',
            'stock_yards' => 'decimal:2',
            'reserved_yards' => 'decimal:2',
            'low_stock_threshold_yards' => 'decimal:2',
            'min_order_yards' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Fabric, $this> */
    public function fabric(): BelongsTo
    {
        return $this->belongsTo(Fabric::class);
    }

    public function pricePerYard(): Money
    {
        return Money::ofMinor((int) $this->price_per_yard_kobo);
    }

    public function availableYards(): float
    {
        return (float) $this->stock_yards - (float) $this->reserved_yards;
    }

    public function isLowStock(): bool
    {
        return $this->availableYards() <= (float) $this->low_stock_threshold_yards;
    }

    public function hasStockFor(float $yards): bool
    {
        return $this->availableYards() >= $yards;
    }

    public function displayName(): string
    {
        $fabric = $this->relationLoaded('fabric') ? $this->fabric : $this->fabric()->first();
        $name = $fabric === null ? 'Fabric' : (string) $fabric->name;

        return trim($name.' — '.$this->colour_name);
    }

    public function placeholderLabel(): string
    {
        return $this->displayName();
    }

    public function toSpec(): FabricVariantSpec
    {
        return new FabricVariantSpec(
            id: (int) $this->id,
            sku: (string) $this->sku,
            name: $this->displayName(),
            pricePerYardKobo: (int) $this->price_per_yard_kobo,
            stockYardsHundredths: (int) round($this->availableYards() * 100),
            minOrderYardsHundredths: (int) round(((float) $this->min_order_yards) * 100),
        );
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<FabricVariant>  $query */
    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
