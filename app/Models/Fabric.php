<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasMediaAssets;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $fabric_material_id
 * @property string $slug
 * @property string $name
 * @property int|null $gsm
 * @property string|null $width_inches
 * @property bool $is_active
 * @property-read FabricMaterial|null $material
 */
class Fabric extends Model
{
    /** @use HasFactory<\Database\Factories\FabricFactory> */
    use HasFactory, HasMediaAssets;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'gsm' => 'integer',
            'width_inches' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<FabricMaterial, $this> */
    public function material(): BelongsTo
    {
        return $this->belongsTo(FabricMaterial::class, 'fabric_material_id');
    }

    /** @return HasMany<FabricVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(FabricVariant::class);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Fabric>  $query */
    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
