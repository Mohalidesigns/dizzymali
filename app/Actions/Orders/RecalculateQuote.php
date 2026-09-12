<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Pricing\Quote;
use App\Domain\Pricing\QuoteCalculator;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * Recalculates an order's totals from the catalogue and writes them back.
 *
 * Called on every wizard step and again at submission. A price arriving from
 * the client is input to be validated against, never a value to be trusted.
 */
class RecalculateQuote
{
    public function __construct(
        private readonly QuoteCalculator $calculator,
        private readonly BuildQuoteContext $buildContext,
    ) {}

    public function handle(Order $order, bool $persist = true): Quote
    {
        $order->loadMissing([
            'items.garmentType.yardageRules.measurementField',
            'items.fabricVariant.fabric',
            'items.options',
            'user',
            'shippingAddress',
        ]);

        $useSnapshots = $order->status->isLocked();

        $inputs = $order->items
            ->map(fn (OrderItem $item) => $item->toPricingInput($useSnapshots))
            ->values()
            ->all();

        // Shipping depends on weight, weight depends on yardage, yardage
        // depends on the line items — so price once with zero shipping to get
        // the weight, then price again for real.
        $probe = $this->calculator->calculate($inputs, $this->buildContext->handle($order, 0, $useSnapshots));
        $context = $this->buildContext->handle($order, $probe->totalWeightGrams, $useSnapshots);
        $quote = $this->calculator->calculate($inputs, $context);

        if ($persist) {
            $this->persist($order, $quote);
        }

        return $quote;
    }

    private function persist(Order $order, Quote $quote): void
    {
        foreach ($quote->items as $index => $line) {
            $item = $order->items[$index] ?? null;

            if ($item === null) {
                continue;
            }

            $item->forceFill([
                'yards_base' => $line->yardage->baseHundredths / 100,
                'yards_size_adjustment' => $line->yardage->sizeAdjustmentHundredths / 100,
                'yards_required' => $line->yardage->totalHundredths() / 100,
                'fabric_cost_kobo' => $line->fabricCost->minor,
                'sewing_cost_kobo' => $line->sewingCost->minor,
                'options_cost_kobo' => $line->optionsCost->minor,
                'line_total_kobo' => $line->lineTotal->minor,
            ])->save();
        }

        $order->forceFill([
            'fabric_total_kobo' => $quote->fabricTotal->minor,
            'sewing_total_kobo' => $quote->sewingTotal->minor,
            'options_total_kobo' => $quote->optionsTotal->minor,
            'subtotal_kobo' => $quote->subtotal->minor,
            'shipping_kobo' => $quote->shipping->minor,
            'total_kobo' => $quote->total->minor,
            'display_total_minor' => $quote->displayTotalMinor,
        ])->save();
    }
}
