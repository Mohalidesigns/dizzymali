<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $measurement_profile_id
 * @property int $measurement_field_id
 * @property string $value_inches
 * @property-read MeasurementField|null $measurementField
 */
class MeasurementValue extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value_inches' => 'decimal:2'];
    }

    /** @return BelongsTo<MeasurementProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(MeasurementProfile::class, 'measurement_profile_id');
    }

    /** @return BelongsTo<MeasurementField, $this> */
    public function measurementField(): BelongsTo
    {
        return $this->belongsTo(MeasurementField::class);
    }

    /** Presentation only. The database stores inches and nothing else. */
    public function inCentimetres(): float
    {
        return round(((float) $this->value_inches) * 2.54, 1);
    }
}
