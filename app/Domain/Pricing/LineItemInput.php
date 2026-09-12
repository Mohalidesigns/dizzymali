<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final readonly class LineItemInput
{
    /**
     * @param  array<string,int>  $measurementsHundredths  field key => inches × 100
     * @param  list<SelectedOption>  $options
     */
    public function __construct(
        public GarmentTypeSpec $garmentType,
        public ?FabricVariantSpec $fabricVariant = null,
        public int $quantity = 1,
        public array $measurementsHundredths = [],
        public array $options = [],
        public int $customerExtraYardsHundredths = 0,
    ) {}
}
