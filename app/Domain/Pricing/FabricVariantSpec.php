<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final readonly class FabricVariantSpec
{
    public function __construct(
        public int $id,
        public string $sku,
        public string $name,
        public int $pricePerYardKobo,
        public int $stockYardsHundredths = 0,
        public int $minOrderYardsHundredths = 100,
    ) {}
}
