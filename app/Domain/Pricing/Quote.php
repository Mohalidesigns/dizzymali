<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

use App\Support\Money;

final readonly class Quote
{
    /** @param  list<LineItemQuote>  $items */
    public function __construct(
        public array $items,
        public Money $fabricTotal,
        public Money $sewingTotal,
        public Money $optionsTotal,
        public Money $subtotal,
        public Money $shipping,
        public Money $discount,
        public Money $tax,
        public Money $total,
        public int $totalWeightGrams,
        public int $leadTimeDays,
        public string $displayCurrency = 'NGN',
        public ?int $displayTotalMinor = null,
        public ?int $fxRate1e8 = null,
        public ?int $fxMarginBasisPoints = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (LineItemQuote $i) => $i->toArray(), $this->items),
            'fabric_total_kobo' => $this->fabricTotal->minor,
            'sewing_total_kobo' => $this->sewingTotal->minor,
            'options_total_kobo' => $this->optionsTotal->minor,
            'subtotal_kobo' => $this->subtotal->minor,
            'shipping_kobo' => $this->shipping->minor,
            'discount_kobo' => $this->discount->minor,
            'tax_kobo' => $this->tax->minor,
            'total_kobo' => $this->total->minor,
            'total_weight_grams' => $this->totalWeightGrams,
            'lead_time_days' => $this->leadTimeDays,
            'display' => [
                'currency' => $this->displayCurrency,
                'total_minor' => $this->displayTotalMinor,
                'fx_rate_1e8' => $this->fxRate1e8,
                'fx_margin_bp' => $this->fxMarginBasisPoints,
            ],
        ];
    }
}
