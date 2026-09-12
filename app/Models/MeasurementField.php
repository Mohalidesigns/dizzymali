<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MeasurementGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property MeasurementGroup $group
 * @property string $key
 * @property string $label
 * @property string|null $help_text
 * @property string $unit_type
 * @property string $min_inches
 * @property string $max_inches
 * @property int $sort_order
 * @property bool $is_active
 * @property \Illuminate\Database\Eloquent\Relations\Pivot|null $pivot
 */
class MeasurementField extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'group' => MeasurementGroup::class,
            'min_inches' => 'decimal:2',
            'max_inches' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<GarmentType, $this> */
    public function garmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(GarmentType::class, 'garment_type_measurement_field');
    }

    /** A 12-inch chest is a typo. Catch it before the fabric is cut. */
    public function isPlausible(float $inches): bool
    {
        return $inches >= (float) $this->min_inches && $inches <= (float) $this->max_inches;
    }
}
