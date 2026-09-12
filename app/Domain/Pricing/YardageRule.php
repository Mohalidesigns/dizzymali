<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

/**
 * "An Agbada for a chest over 46 inches needs half a yard more cloth."
 *
 * Admin-editable, because the real number is a workshop fact, not a constant a
 * developer should be guessing at.
 */
final readonly class YardageRule
{
    public function __construct(
        public string $fieldKey,
        public int $thresholdHundredths,
        public int $additionalYardsHundredths,
    ) {}
}
