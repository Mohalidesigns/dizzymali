<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\CustomerStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemInspiration;
use App\Models\OrderItemOption;
use App\Models\OrderProgressPhoto;
use App\Models\OrderStatusEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $currency = (string) $this->currency_code;
        $stage = $this->customerStage();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'wizard_step' => $this->wizard_step,
            'is_editable' => $this->isEditable(),
            'service_level' => $this->service_level,
            'customer_notes' => $this->customer_notes,
            'currency_code' => $currency,

            'totals' => [
                'fabric' => MoneyResource::make((int) $this->fabric_total_kobo),
                'sewing' => MoneyResource::make((int) $this->sewing_total_kobo),
                'options' => MoneyResource::make((int) $this->options_total_kobo),
                'subtotal' => MoneyResource::make((int) $this->subtotal_kobo),
                'shipping' => MoneyResource::make((int) $this->shipping_kobo),
                'discount' => MoneyResource::make((int) $this->discount_kobo),
                'total' => MoneyResource::make((int) $this->total_kobo),
                'paid' => MoneyResource::make((int) $this->amount_paid_kobo),
                'balance_due' => MoneyResource::make($this->balanceDue()->minor),
                'display_total' => $this->display_total_minor === null
                    ? null
                    : MoneyResource::make((int) $this->display_total_minor, $currency),
            ],

            'timeline' => [
                'current' => $stage->value,
                'current_label' => $stage->label(),
                'current_description' => $stage->description(),
                'position' => $stage->position(),
                'stages' => array_map(fn (CustomerStage $s) => [
                    'key' => $s->value,
                    'label' => $s->label(),
                    'description' => $s->description(),
                    'reached' => $s->position() <= $stage->position(),
                ], CustomerStage::timeline()),
            ],

            'promised_at' => $this->promised_at?->toDateString(),
            'placed_at' => $this->placed_at?->toIso8601String(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'is_overdue' => $this->isOverdue(),
            'shipping_address' => $this->shipping_address_snapshot
                ?? $this->whenLoaded('shippingAddress', fn () => $this->shippingAddress?->snapshot()),

            'items' => $this->whenLoaded('items', fn (): array => $this->mapItems()),

            'events' => $this->whenLoaded('statusEvents', fn (): array => $this->mapEvents($request)),

            'progress_photos' => $this->whenLoaded('progressPhotos', fn (): array => $this->mapPhotos()),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function mapItems(): array
    {
        return $this->items
            ->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'garment_type' => $item->relationLoaded('garmentType') && $item->garmentType !== null
                    ? ['id' => $item->garmentType->id, 'slug' => $item->garmentType->slug, 'name' => $item->garmentType->name]
                    : $item->garment_type_snapshot,
                'fabric_variant' => $item->fabric_variant_snapshot
                    ?? ($item->relationLoaded('fabricVariant') && $item->fabricVariant !== null
                        ? [
                            'id' => $item->fabricVariant->id,
                            'name' => $item->fabricVariant->displayName(),
                            'colour_hex' => $item->fabricVariant->colour_hex,
                            'price_per_yard_kobo' => (int) $item->fabricVariant->price_per_yard_kobo,
                        ]
                        : null),
                'yards' => [
                    'base' => (float) $item->yards_base,
                    'size_adjustment' => (float) $item->yards_size_adjustment,
                    'customer_extra' => (float) $item->yards_customer_extra,
                    'total' => (float) $item->yards_required,
                ],
                'costs' => [
                    'fabric' => MoneyResource::make((int) $item->fabric_cost_kobo),
                    'sewing' => MoneyResource::make((int) $item->sewing_cost_kobo),
                    'options' => MoneyResource::make((int) $item->options_cost_kobo),
                    'line_total' => MoneyResource::make((int) $item->line_total_kobo),
                ],
                'style_notes' => $item->style_notes,
                'measurement_profile_id' => $item->measurement_profile_id,
                'measurements' => is_array($item->measurement_snapshot)
                    ? ($item->measurement_snapshot['values'] ?? [])
                    : [],
                'options' => $item->relationLoaded('options')
                    ? $item->options->map(fn (OrderItemOption $o): array => [
                        'id' => $o->garment_option_id,
                        'group' => $o->group_name,
                        'name' => $o->option_name,
                        'surcharge' => MoneyResource::make((int) $o->surcharge_kobo),
                    ])->values()->all()
                    : [],
                'inspirations' => $item->relationLoaded('inspirations')
                    ? $item->inspirations->map(fn (OrderItemInspiration $img): array => [
                        'id' => $img->id,
                        'url' => $img->temporaryUrl(),
                        'original_name' => $img->original_name,
                    ])->values()->all()
                    : [],
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string,mixed>> */
    private function mapEvents(Request $request): array
    {
        $isStaff = $request->user()?->isBackOffice() ?? false;

        return $this->statusEvents
            ->filter(fn (OrderStatusEvent $e): bool => $e->is_customer_visible || $isStaff)
            ->map(fn (OrderStatusEvent $e): array => [
                'id' => $e->id,
                'to_status' => $e->to_status->value,
                'label' => $e->to_status->label(),
                'stage' => $e->to_status->customerStage()->value,
                'note' => $e->note,
                'at' => $e->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @return list<array<string,mixed>> */
    private function mapPhotos(): array
    {
        return $this->progressPhotos
            ->map(fn (OrderProgressPhoto $p): array => [
                'id' => $p->id,
                'stage' => $p->stage,
                'caption' => $p->caption,
                'alt_text' => $p->alt_text,
                'at' => $p->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
