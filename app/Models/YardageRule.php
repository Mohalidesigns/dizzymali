<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $garment_type_id
 * @property int $measurement_field_id
 * @property string $threshold_inches
 * @property string $additional_yards
 * @property bool $is_active
 * @property-read MeasurementField|null $measurementField
 */
class YardageRule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'threshold_inches' => 'decimal:2',
            'additional_yards' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<GarmentType, $this> */
    public function garmentType(): BelongsTo
    {
        return $this->belongsTo(GarmentType::class);
    }

    /** @return BelongsTo<MeasurementField, $this> */
    public function measurementField(): BelongsTo
    {
        return $this->belongsTo(MeasurementField::class);
    }
}
