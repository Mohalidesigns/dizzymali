<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MeasurementProfile;
use App\Models\MeasurementValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MeasurementProfile */
class MeasurementProfileResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit_preference' => $this->unit_preference,
            'source' => $this->source,
            'review_status' => $this->review_status,
            'is_default' => $this->is_default,
            'notes' => $this->notes,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'values' => $this->whenLoaded('values', fn () => $this->values
                ->filter(fn (MeasurementValue $v) => $v->measurementField !== null)
                ->map(fn (MeasurementValue $v) => [
                    'key' => $v->measurementField->key,
                    'label' => $v->measurementField->label,
                    'group' => $v->measurementField->group->value,
                    'inches' => (float) $v->value_inches,
                    'cm' => $v->inCentimetres(),
                ])->values()),
        ];
    }
}
