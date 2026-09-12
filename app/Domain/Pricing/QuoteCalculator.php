<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Support\Money;

/**
 * The commercial heart of DizzyMali.
 *
 * Pure, deterministic, framework-free. Give it the same inputs a year from now
 * and it returns the same total to the kobo. It knows nothing about HTTP,
 * Eloquent or the session, which is precisely why it can be trusted with money.
 *
 * Per line item:
 *     fabric_cost = yards_required × variant.price_per_yard_kobo
 *     sewing_cost = garment.base_sewing_cost + express surcharge
 *     options     = Σ option surcharges
 *     line_total  = (fabric + sewing + options) × quantity
 *
 * Per order:
 *     subtotal = Σ line_total
 *     total    = subtotal + shipping − discount + tax
 *     display  = round_up(total × fx_rate × (1 + margin), currency rounding)
 */
final class QuoteCalculator
{
    public function __construct(
        private readonly YardageCalculator $yardage = new YardageCalculator,
    ) {}

    /** @param  list<LineItemInput>  $items */
    public function calculate(array $items, QuoteContext $context): Quote
    {
        $lines = [];
        $fabricTotal = Money::zero();
        $sewingTotal = Money::zero();
        $optionsTotal = Money::zero();
        $subtotal = Money::zero();
        $weightGrams = 0;
        $leadTimeDays = 0;

        foreach ($items as $item) {
            $line = $this->calculateLine($item, $context);

            $lines[] = $line;
            $fabricTotal = $fabricTotal->plus($line->fabricCost->times($line->quantity));
            $sewingTotal = $sewingTotal->plus($line->sewingCost->times($line->quantity));
            $optionsTotal = $optionsTotal->plus($line->optionsCost->times($line->quantity));
            $subtotal = $subtotal->plus($line->lineTotal);
            $weightGrams += $line->weightGrams * $line->quantity;
            $leadTimeDays = max($leadTimeDays, $line->leadTimeDays);
        }

        $total = $subtotal
            ->plus($context->shipping)
            ->minus($context->discount)
            ->plus($context->tax)
            ->atLeastZero();

        [$displayMinor, $rate, $margin] = $this->toDisplayCurrency($total, $context);

        return new Quote(
            items: $lines,
            fabricTotal: $fabricTotal,
            sewingTotal: $sewingTotal,
            optionsTotal: $optionsTotal,
            subtotal: $subtotal,
            shipping: $context->shipping,
            discount: $context->discount,
            tax: $context->tax,
            total: $total,
            totalWeightGrams: $weightGrams,
            leadTimeDays: $leadTimeDays,
            displayCurrency: $context->displayCurrency,
            displayTotalMinor: $displayMinor,
            fxRate1e8: $rate,
            fxMarginBasisPoints: $margin,
        );
    }

    public function calculateLine(LineItemInput $item, QuoteContext $context): LineItemQuote
    {
        $yardage = $this->yardage->calculate($item);
        $yards = $yardage->totalHundredths();

        $fabricCost = $item->fabricVariant === null
            ? Money::zero()
            : Money::ofMinor($item->fabricVariant->pricePerYardKobo)->timesHundredths($yards);

        $sewingCost = Money::ofMinor($item->garmentType->baseSewingCostKobo);

        if ($context->isExpress && $context->expressSurchargeBasisPoints > 0) {
            $sewingCost = $sewingCost->plusBasisPoints($context->expressSurchargeBasisPoints);
        }

        $optionsCost = Money::zero();
        $additionalLeadDays = 0;

        foreach ($item->options as $option) {
            $optionsCost = $optionsCost->plus(Money::ofMinor($option->surchargeKobo));
            $additionalLeadDays = max($additionalLeadDays, $option->additionalLeadDays);
        }

        $quantity = max(1, $item->quantity);
        $lineTotal = $fabricCost->plus($sewingCost)->plus($optionsCost)->times($quantity);

        $weight = $item->garmentType->baseWeightGrams
            + intdiv($item->garmentType->gramsPerYard * $yards, 100);

        return new LineItemQuote(
            garmentType: $item->garmentType,
            fabricVariant: $item->fabricVariant,
            quantity: $quantity,
            yardage: $yardage,
            fabricCost: $fabricCost,
            sewingCost: $sewingCost,
            optionsCost: $optionsCost,
            lineTotal: $lineTotal,
            weightGrams: $weight,
            leadTimeDays: $item->garmentType->leadTimeDays + $additionalLeadDays,
            options: $item->options,
        );
    }

    /**
     * @return array{0:?int,1:?int,2:?int}
     */
    private function toDisplayCurrency(Money $total, QuoteContext $context): array
    {
        if ($context->displayCurrency === 'NGN' || $context->fxRate1e8 === null) {
            return [$total->minor, null, null];
        }

        $converted = $total
            ->convertTo($context->displayCurrency, $context->fxRate1e8, $context->displayDecimals)
            ->plusBasisPoints($context->fxMarginBasisPoints)
            ->roundUpToNearest($context->displayRoundingMinor);

        return [$converted->minor, $context->fxRate1e8, $context->fxMarginBasisPoints];
    }
}
