<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

/**
 * Everything the pricing engine needs to know about a garment type, with no
 * Eloquent attached so it can be constructed in a unit test in one line.
 */
final readonly class GarmentTypeSpec
{
    /** @param  list<YardageRule>  $yardageRules */
    public function __construct(
        public int $id,
        public string $name,
        public int $baseSewingCostKobo,
        public int $defaultYardageHundredths,
        public int $leadTimeDays = 21,
        public int $baseWeightGrams = 600,
        public int $gramsPerYard = 220,
        public array $yardageRules = [],
    ) {}
}
