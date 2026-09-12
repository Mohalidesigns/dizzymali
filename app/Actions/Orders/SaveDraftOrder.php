<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\GarmentOption;
use App\Models\MeasurementProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Persists one step of the order wizard server-side.
 *
 * The draft is saved after every step so a customer who closes the tab in Lagos
 * resumes on their phone in Manchester. Nothing here accepts a price.
 */
class SaveDraftOrder
{
    public function __construct(private readonly RecalculateQuote $recalculate) {}

    public function currentDraft(User $user): Order
    {
        return $user->orders()->drafts()->latest()->first()
            ?? Order::create([
                'user_id' => $user->id,
                'status' => OrderStatus::Draft,
                'currency_code' => $user->preferred_currency ?: 'NGN',
                'wizard_step' => 1,
            ]);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function handle(Order $order, array $data): Order
    {
        abort_unless($order->isEditable(), 409, 'This order can no longer be changed.');

        return DB::transaction(function () use ($order, $data) {
            $item = $order->items()->first();

            if (isset($data['garment_type_id'])) {
                $item = $this->upsertItem($order, $item, ['garment_type_id' => $data['garment_type_id']]);
            }

            if (array_key_exists('fabric_variant_id', $data)) {
                $item = $this->upsertItem($order, $item, ['fabric_variant_id' => $data['fabric_variant_id']]);
            }

            if (array_key_exists('measurement_profile_id', $data) && $item !== null) {
                $this->attachMeasurements($order, $item, $data['measurement_profile_id']);
            }

            if ($item !== null) {
                $itemUpdates = array_filter([
                    'quantity' => $data['quantity'] ?? null,
                    'yards_customer_extra' => $data['extra_yards'] ?? null,
                    'style_notes' => $data['style_notes'] ?? null,
                ], static fn ($v) => $v !== null);

                if ($itemUpdates !== []) {
                    $item->forceFill($itemUpdates)->save();
                }

                if (array_key_exists('option_ids', $data)) {
                    $this->syncOptions($item, (array) $data['option_ids']);
                }
            }

            $orderUpdates = array_filter([
                'service_level' => $data['service_level'] ?? null,
                'currency_code' => isset($data['currency_code']) ? strtoupper($data['currency_code']) : null,
                'customer_notes' => $data['customer_notes'] ?? null,
                'wizard_step' => $data['wizard_step'] ?? null,
            ], static fn ($v) => $v !== null);

            if (array_key_exists('shipping_address_id', $data)) {
                $orderUpdates['shipping_address_id'] = $data['shipping_address_id'];
            }

            if ($orderUpdates !== []) {
                $order->forceFill($orderUpdates)->save();
            }

            $this->recalculate->handle($order->refresh());

            return $order->refresh();
        });
    }

    /** @param  array<string,mixed>  $attributes */
    private function upsertItem(Order $order, ?OrderItem $item, array $attributes): OrderItem
    {
        if ($item === null) {
            return $order->items()->create($attributes + ['quantity' => 1]);
        }

        $item->forceFill($attributes)->save();

        return $item;
    }

    private function attachMeasurements(Order $order, OrderItem $item, ?int $profileId): void
    {
        if ($profileId === null) {
            $item->forceFill(['measurement_profile_id' => null, 'measurement_snapshot' => null])->save();

            return;
        }

        $profile = MeasurementProfile::query()
            ->where('user_id', $order->user_id)   // never another customer's measurements
            ->with('values.measurementField')
            ->findOrFail($profileId);

        $item->forceFill([
            'measurement_profile_id' => $profile->id,
            'measurement_snapshot' => $profile->snapshot(),
        ])->save();
    }

    /** @param  list<int>  $optionIds */
    private function syncOptions(OrderItem $item, array $optionIds): void
    {
        $item->options()->delete();

        if ($optionIds === []) {
            return;
        }

        $options = GarmentOption::query()
            ->whereIn('id', $optionIds)
            ->where('is_active', true)
            ->with('group')
            ->get();

        foreach ($options as $option) {
            $item->options()->create([
                'garment_option_id' => $option->id,
                'group_name' => (string) $option->group?->name,
                'option_name' => (string) $option->name,
                'surcharge_kobo' => (int) $option->surcharge_kobo,   // priced from the catalogue
                'additional_yards' => $option->additional_yards,
            ]);
        }
    }
}
