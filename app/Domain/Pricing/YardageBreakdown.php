<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

/**
 * All values are yards × 100, matching DECIMAL(5,2) in the database.
 */
final readonly class YardageBreakdown
{
    /** @param  array<string,int>  $appliedRules  field key => additional yards × 100 */
    public function __construct(
        public int $baseHundredths,
        public int $sizeAdjustmentHundredths,
        public int $optionsHundredths,
        public int $customerExtraHundredths,
        public array $appliedRules = [],
    ) {}

    public function totalHundredths(): int
    {
        return $this->baseHundredths
            + $this->sizeAdjustmentHundredths
            + $this->optionsHundredths
            + $this->customerExtraHundredths;
    }

    public function totalYards(): string
    {
        return number_format($this->totalHundredths() / 100, 2, '.', '');
    }

    /** Human-readable, for the "why does it cost this" panel the customer sees. */
    public function explanation(string $garmentName): string
    {
        $parts = [sprintf('%s base %s yd', $garmentName, self::fmt($this->baseHundredths))];

        if ($this->sizeAdjustmentHundredths > 0) {
            $parts[] = sprintf('+%s yd for your size', self::fmt($this->sizeAdjustmentHundredths));
        }

        if ($this->optionsHundredths > 0) {
            $parts[] = sprintf('+%s yd for selected options', self::fmt($this->optionsHundredths));
        }

        if ($this->customerExtraHundredths > 0) {
            $parts[] = sprintf('+%s yd extra you asked for', self::fmt($this->customerExtraHundredths));
        }

        return implode(', ', $parts);
    }

    private static function fmt(int $hundredths): string
    {
        return rtrim(rtrim(number_format($hundredths / 100, 2, '.', ''), '0'), '.') ?: '0';
    }
}
