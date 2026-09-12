<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\GarmentOption;
use App\Models\GarmentOptionGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GarmentOptionGroup */
class GarmentOptionGroupResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'help_text' => $this->help_text,
            'is_required' => $this->is_required,
            'allows_multiple' => $this->allows_multiple,
            'options' => $this->whenLoaded('options', fn (): array => $this->mapOptions()),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function mapOptions(): array
    {
        return $this->options
            ->map(fn (GarmentOption $o): array => [
                'id' => $o->id,
                'slug' => $o->slug,
                'name' => $o->name,
                'description' => $o->description,
                'surcharge' => MoneyResource::make((int) $o->surcharge_kobo),
                'additional_yards' => (float) $o->additional_yards,
                'additional_lead_days' => $o->additional_lead_days,
                'is_default' => $o->is_default,
            ])
            ->values()
            ->all();
    }
}
