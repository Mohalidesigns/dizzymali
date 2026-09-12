<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Pricing\SelectedOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $garment_option_group_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int $surcharge_kobo
 * @property string $additional_yards
 * @property int $additional_lead_days
 * @property bool $is_default
 * @property-read GarmentOptionGroup|null $group
 */
class GarmentOption extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'surcharge_kobo' => 'integer',
            'additional_yards' => 'decimal:2',
            'additional_lead_days' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<GarmentOptionGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(GarmentOptionGroup::class, 'garment_option_group_id');
    }

    public function toSpec(): SelectedOption
    {
        $group = $this->relationLoaded('group') ? $this->group : $this->group()->first();
        $groupName = $group === null ? '' : (string) $group->name;

        return new SelectedOption(
            id: (int) $this->id,
            groupName: $groupName,
            optionName: (string) $this->name,
            surchargeKobo: (int) $this->surcharge_kobo,
            additionalYardsHundredths: (int) round(((float) $this->additional_yards) * 100),
            additionalLeadDays: (int) $this->additional_lead_days,
        );
    }
}
