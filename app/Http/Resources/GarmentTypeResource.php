<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\GarmentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GarmentType */
class GarmentTypeResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'default_yardage' => (float) $this->default_yardage,
            'lead_time_days' => $this->lead_time_days,
            'requires_top_measurements' => $this->requires_top_measurements,
            'requires_trouser_measurements' => $this->requires_trouser_measurements,
            'sewing_cost' => MoneyResource::make((int) $this->base_sewing_cost_kobo),
            // Real photograph if one exists, generated placeholder if not.
            'image' => $this->resource->imageFor('hero', 900, 1125),
            'measurement_fields' => MeasurementFieldResource::collection(
                $this->whenLoaded('measurementFields'),
            ),
            'option_groups' => GarmentOptionGroupResource::collection(
                $this->whenLoaded('optionGroups'),
            ),
        ];
    }
}
