<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\MeasurementField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MeasurementField */
class MeasurementFieldResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'group' => $this->group->value,
            'label' => $this->label,
            'help_text' => $this->help_text,
            'unit_type' => $this->unit_type,
            'min_inches' => (float) $this->min_inches,
            'max_inches' => (float) $this->max_inches,
            'is_required' => (bool) ($this->pivot->is_required ?? true),
        ];
    }
}
