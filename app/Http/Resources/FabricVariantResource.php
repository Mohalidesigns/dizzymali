<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FabricVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FabricVariant */
class FabricVariantResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $fabric = $this->whenLoaded('fabric');

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'colour_name' => $this->colour_name,
            'colour_hex' => $this->colour_hex,
            'pattern' => $this->pattern,
            'price_per_yard' => MoneyResource::make((int) $this->price_per_yard_kobo),
            'available_yards' => round($this->availableYards(), 2),
            'is_low_stock' => $this->isLowStock(),
            'min_order_yards' => (float) $this->min_order_yards,
            'fabric' => $this->whenLoaded('fabric', fn () => [
                'id' => $fabric->id,
                'slug' => $fabric->slug,
                'name' => $fabric->name,
                'gsm' => $fabric->gsm,
                'width_inches' => $fabric->width_inches ? (float) $fabric->width_inches : null,
                'origin' => $fabric->origin,
                'care_instructions' => $fabric->care_instructions,
                'drape_notes' => $fabric->drape_notes,
                'material' => $fabric->relationLoaded('material') && $fabric->material
                    ? ['id' => $fabric->material->id, 'slug' => $fabric->material->slug, 'name' => $fabric->material->name]
                    : null,
            ]),
        ];
    }
}
