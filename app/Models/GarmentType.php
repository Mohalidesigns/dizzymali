<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasMediaAssets;
use App\Domain\Pricing\GarmentTypeSpec;
use App\Domain\Pricing\YardageRule as YardageRuleSpec;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $tagline
 * @property string|null $description
 * @property int $base_sewing_cost_kobo
 * @property string $default_yardage
 * @property int $base_weight_grams
 * @property int $grams_per_yard
 * @property bool $requires_top_measurements
 * @property bool $requires_trouser_measurements
 * @property int $lead_time_days
 * @property bool $is_active
 * @property int $sort_order
 * @property-read \Illuminate\Database\Eloquent\Collection<int,MeasurementField> $measurementFields
 * @property-read \Illuminate\Database\Eloquent\Collection<int,YardageRule> $yardageRules
 */
class GarmentType extends Model
{
    /** @use HasFactory<\Database\Factories\GarmentTypeFactory> */
    use HasFactory, HasMediaAssets;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'base_sewing_cost_kobo' => 'integer',
            'default_yardage' => 'decimal:2',
            'base_weight_grams' => 'integer',
            'grams_per_yard' => 'integer',
            'requires_top_measurements' => 'boolean',
            'requires_trouser_measurements' => 'boolean',
            'lead_time_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsToMany<MeasurementField, $this> */
    public function measurementFields(): BelongsToMany
    {
        return $this->belongsToMany(MeasurementField::class, 'garment_type_measurement_field')
            ->withPivot(['is_required', 'sort_order'])
            ->orderBy('garment_type_measurement_field.sort_order');
    }

    /** @return BelongsToMany<GarmentOptionGroup, $this> */
    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(GarmentOptionGroup::class, 'garment_type_option_group')
            ->withPivot('sort_order')
            ->orderBy('garment_type_option_group.sort_order');
    }

    /** @return HasMany<YardageRule, $this> */
    public function yardageRules(): HasMany
    {
        return $this->hasMany(YardageRule::class);
    }

    public function baseSewingCost(): Money
    {
        return Money::ofMinor((int) $this->base_sewing_cost_kobo);
    }

    /** Hand the pricing engine a plain, framework-free description of this garment. */
    public function toSpec(): GarmentTypeSpec
    {
        $rules = $this->relationLoaded('yardageRules')
            ? $this->yardageRules
            : $this->yardageRules()->where('is_active', true)->with('measurementField')->get();

        return new GarmentTypeSpec(
            id: (int) $this->id,
            name: (string) $this->name,
            baseSewingCostKobo: (int) $this->base_sewing_cost_kobo,
            defaultYardageHundredths: (int) round(((float) $this->default_yardage) * 100),
            leadTimeDays: (int) $this->lead_time_days,
            baseWeightGrams: (int) $this->base_weight_grams,
            gramsPerYard: (int) $this->grams_per_yard,
            yardageRules: $rules
                ->filter(fn (YardageRule $r) => $r->is_active && $r->measurementField !== null)
                ->map(fn (YardageRule $r) => new YardageRuleSpec(
                    fieldKey: (string) $r->measurementField->key,
                    thresholdHundredths: (int) round(((float) $r->threshold_inches) * 100),
                    additionalYardsHundredths: (int) round(((float) $r->additional_yards) * 100),
                ))
                ->values()
                ->all(),
        );
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<GarmentType>  $query */
    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
