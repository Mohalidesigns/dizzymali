<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

/**
 * Works out how much cloth a garment actually needs.
 *
 * Charging a 52-inch chest the same yardage as a 38-inch chest means either
 * losing money on the large order or overcharging the small one. The rule is
 * therefore explicit, admin-editable and shown to the customer.
 *
 * Where several rules exist for the same measurement field — a tier ladder such
 * as 44in +0.25, 48in +0.50, 52in +0.75 — only the highest one the customer
 * clears is applied. Rules on different fields add together.
 */
final class YardageCalculator
{
    public function calculate(LineItemInput $item): YardageBreakdown
    {
        $garment = $item->garmentType;

        /** @var array<string,int> $best */
        $best = [];

        foreach ($garment->yardageRules as $rule) {
            $measured = $item->measurementsHundredths[$rule->fieldKey] ?? null;

            if ($measured === null || $measured <= $rule->thresholdHundredths) {
                continue;
            }

            $current = $best[$rule->fieldKey] ?? 0;
            $best[$rule->fieldKey] = max($current, $rule->additionalYardsHundredths);
        }

        $optionYards = 0;

        foreach ($item->options as $option) {
            $optionYards += $option->additionalYardsHundredths;
        }

        return new YardageBreakdown(
            baseHundredths: $garment->defaultYardageHundredths,
            sizeAdjustmentHundredths: array_sum($best),
            optionsHundredths: $optionYards,
            customerExtraHundredths: max(0, $item->customerExtraYardsHundredths),
            appliedRules: $best,
        );
    }
}
