<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

final readonly class SelectedOption
{
    public function __construct(
        public ?int $id,
        public string $groupName,
        public string $optionName,
        public int $surchargeKobo = 0,
        public int $additionalYardsHundredths = 0,
        public int $additionalLeadDays = 0,
    ) {}
}
