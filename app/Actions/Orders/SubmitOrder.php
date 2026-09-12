<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Currency\CurrencyConverter;
use App\Domain\Orders\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Exceptions\OrderNotSubmittable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Freezes a draft into a real order.
 *
 * This is the moment everything that must not change again is snapshotted:
 * the measurements, the garment and fabric prices, the shipping address and
 * the FX rate. After this the catalogue can move freely without touching a
 * garment that is already being cut.
 */
class SubmitOrder
{
    public function __construct(
        private readonly RecalculateQuote $recalculate,
        private readonly OrderStateMachine $states,
        private readonly CurrencyConverter $currency,
    ) {}

    public function handle(Order $order, ?User $actor = null): Order
    {
        $this->assertSubmittable($order);

        return DB::transaction(function () use ($order, $actor) {
            $quote = $this->recalculate->handle($order);

            $this->snapshotItems($order);
            $this->snapshotAddress($order);
            $this->freezeFxRate($order);

            $order->forceFill([
                'promised_at' => now()->addDays($quote->leadTimeDays),
            ])->save();

            return $this->states->transition(
                $order,
                OrderStatus::Submitted,
                $actor ?? $order->user,
                'Order submitted by the customer.',
            );
        });
    }

    /** @throws OrderNotSubmittable */
    public function assertSubmittable(Order $order): void
    {
        $order->loadMissing('items.garmentType.measurementFields', 'items.fabricVariant');

        $reasons = [];

        if ($order->status !== OrderStatus::Draft) {
            $reasons[] = 'It has already been submitted.';
        }

        if ($order->items->isEmpty()) {
            $reasons[] = 'It has no garment on it.';
        }

        foreach ($order->items as $item) {
            if ($item->fabric_variant_id === null) {
                $reasons[] = 'Choose a fabric before submitting.';
            }

            if (! is_array($item->measurement_snapshot) || $item->measurement_snapshot === []) {
                $reasons[] = 'Add your measurements before submitting.';

                continue;
            }

            $missing = $this->missingRequiredFields($item);

            if ($missing !== []) {
                $reasons[] = 'These measurements are still missing: '.implode(', ', $missing).'.';
            }

            if ($item->fabricVariant !== null
                && ! $item->fabricVariant->hasStockFor((float) $item->yards_required * (int) $item->quantity)) {
                $reasons[] = sprintf(
                    '%s does not have enough stock left for this order.',
                    $item->fabricVariant->displayName(),
                );
            }
        }

        if ($order->shipping_address_id === null) {
            $reasons[] = 'Add a delivery address before submitting.';
        }

        if ($reasons !== []) {
            throw new OrderNotSubmittable(array_values(array_unique($reasons)));
        }
    }

    /** @return list<string> */
    private function missingRequiredFields(OrderItem $item): array
    {
        $provided = array_keys($item->measurementsHundredths());
        $missing = [];

        $garment = $item->garmentType;

        if ($garment === null) {
            return [];
        }

        foreach ($garment->measurementFields as $field) {
            $pivot = $field->getRelationValue('pivot');
            $required = $pivot === null || (bool) $pivot->getAttribute('is_required');

            if ($required && ! in_array($field->key, $provided, true)) {
                $missing[] = $field->label;
            }
        }

        return $missing;
    }

    private function snapshotItems(Order $order): void
    {
        foreach ($order->items as $item) {
            $garment = $item->garmentType;
            $variant = $item->fabricVariant;

            $item->forceFill([
                'garment_type_snapshot' => $garment === null ? null : [
                    'id' => $garment->id,
                    'name' => $garment->name,
                    'base_sewing_cost_kobo' => (int) $garment->base_sewing_cost_kobo,
                    'default_yardage_hundredths' => (int) round(((float) $garment->default_yardage) * 100),
                    'lead_time_days' => (int) $garment->lead_time_days,
                    'base_weight_grams' => (int) $garment->base_weight_grams,
                    'grams_per_yard' => (int) $garment->grams_per_yard,
                ],
                'fabric_variant_snapshot' => $variant === null ? null : [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->displayName(),
                    'colour_name' => $variant->colour_name,
                    'colour_hex' => $variant->colour_hex,
                    'price_per_yard_kobo' => (int) $variant->price_per_yard_kobo,
                ],
            ])->save();
        }
    }

    private function snapshotAddress(Order $order): void
    {
        $address = $order->shippingAddress;

        if ($address !== null) {
            $order->forceFill(['shipping_address_snapshot' => $address->snapshot()])->save();
        }
    }

    private function freezeFxRate(Order $order): void
    {
        $code = strtoupper((string) $order->currency_code);

        if ($code === 'NGN') {
            $order->forceFill([
                'fx_rate_used' => null,
                'fx_margin_percent' => null,
                'display_total_minor' => (int) $order->total_kobo,
            ])->save();

            return;
        }

        $rate = $this->currency->displayRateFor($code);

        if ($rate === null) {
            return;
        }

        $order->forceFill([
            'fx_rate_used' => $rate->rate1e8 / 100_000_000,
            'fx_margin_percent' => $rate->marginBasisPoints / 100,
            'display_total_minor' => $this->currency->convert($order->total(), $rate)->minor,
        ])->save();
    }
}
