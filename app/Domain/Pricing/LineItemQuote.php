<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Support\Money;

final readonly class LineItemQuote
{
    /** @param  list<SelectedOption>  $options */
    public function __construct(
        public GarmentTypeSpec $garmentType,
        public ?FabricVariantSpec $fabricVariant,
        public int $quantity,
        public YardageBreakdown $yardage,
        public Money $fabricCost,
        public Money $sewingCost,
        public Money $optionsCost,
        public Money $lineTotal,
        public int $weightGrams,
        public int $leadTimeDays,
        public array $options = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'garment_type_id' => $this->garmentType->id,
            'garment_type_name' => $this->garmentType->name,
            'fabric_variant_id' => $this->fabricVariant?->id,
            'fabric_variant_name' => $this->fabricVariant?->name,
            'price_per_yard_kobo' => $this->fabricVariant?->pricePerYardKobo,
            'quantity' => $this->quantity,
            'yards' => [
                'base' => $this->yardage->baseHundredths / 100,
                'size_adjustment' => $this->yardage->sizeAdjustmentHundredths / 100,
                'options' => $this->yardage->optionsHundredths / 100,
                'customer_extra' => $this->yardage->customerExtraHundredths / 100,
                'total' => $this->yardage->totalHundredths() / 100,
                'explanation' => $this->yardage->explanation($this->garmentType->name),
            ],
            'fabric_cost_kobo' => $this->fabricCost->minor,
            'sewing_cost_kobo' => $this->sewingCost->minor,
            'options_cost_kobo' => $this->optionsCost->minor,
            'line_total_kobo' => $this->lineTotal->minor,
            'weight_grams' => $this->weightGrams,
            'lead_time_days' => $this->leadTimeDays,
            'options' => array_map(fn (SelectedOption $o) => [
                'id' => $o->id,
                'group' => $o->groupName,
                'name' => $o->optionName,
                'surcharge_kobo' => $o->surchargeKobo,
            ], $this->options),
        ];
    }
}
