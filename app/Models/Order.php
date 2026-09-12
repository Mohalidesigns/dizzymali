<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStage;
use App\Enums\OrderStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $reference
 * @property int $user_id
 * @property OrderStatus $status
 * @property int $wizard_step
 * @property int $fabric_total_kobo
 * @property int $sewing_total_kobo
 * @property int $options_total_kobo
 * @property int $subtotal_kobo
 * @property int $shipping_kobo
 * @property int $discount_kobo
 * @property int $tax_kobo
 * @property int $total_kobo
 * @property int $amount_paid_kobo
 * @property string $currency_code
 * @property string|null $fx_rate_used
 * @property string|null $fx_margin_percent
 * @property int|null $display_total_minor
 * @property string $service_level
 * @property int|null $shipping_address_id
 * @property array<string,mixed>|null $shipping_address_snapshot
 * @property int|null $shipping_zone_id
 * @property string|null $customer_notes
 * @property string|null $internal_notes
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $placed_at
 * @property \Illuminate\Support\Carbon|null $promised_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $cancelled_at
 * @property-read User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int,OrderItem> $items
 * @property-read \Illuminate\Database\Eloquent\Collection<int,OrderStatusEvent> $statusEvents
 * @property-read \Illuminate\Database\Eloquent\Collection<int,OrderProgressPhoto> $progressPhotos
 * @property-read Address|null $shippingAddress
 */
class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'wizard_step' => 'integer',
            'fabric_total_kobo' => 'integer',
            'sewing_total_kobo' => 'integer',
            'options_total_kobo' => 'integer',
            'subtotal_kobo' => 'integer',
            'shipping_kobo' => 'integer',
            'discount_kobo' => 'integer',
            'tax_kobo' => 'integer',
            'total_kobo' => 'integer',
            'amount_paid_kobo' => 'integer',
            'display_total_minor' => 'integer',
            'fx_rate_used' => 'decimal:8',
            'fx_margin_percent' => 'decimal:2',
            'shipping_address_snapshot' => 'array',
            'deposit_percent' => 'integer',
            'submitted_at' => 'datetime',
            'placed_at' => 'datetime',
            'promised_at' => 'date',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->reference ??= self::generateReference();
        });
    }

    public static function generateReference(): string
    {
        do {
            $reference = sprintf('DZM-%s-%s', now()->format('ym'), Str::upper(Str::random(4)));
        } while (self::withTrashed()->where('reference', $reference)->exists());

        return $reference;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<OrderStatusEvent, $this> */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->latest();
    }

    /** @return HasMany<OrderProgressPhoto, $this> */
    public function progressPhotos(): HasMany
    {
        return $this->hasMany(OrderProgressPhoto::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @return BelongsTo<Address, $this> */
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    /** @return BelongsTo<ShippingZone, $this> */
    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    public function total(): Money
    {
        return Money::ofMinor((int) $this->total_kobo);
    }

    public function balanceDue(): Money
    {
        return $this->total()
            ->minus(Money::ofMinor((int) $this->amount_paid_kobo))
            ->atLeastZero();
    }

    public function isFullyPaid(): bool
    {
        return $this->balanceDue()->isZero() && (int) $this->total_kobo > 0;
    }

    public function customerStage(): CustomerStage
    {
        return $this->status->customerStage();
    }

    public function isEditable(): bool
    {
        return ! $this->status->isLocked();
    }

    public function isOverdue(): bool
    {
        return $this->promised_at !== null
            && $this->promised_at->isPast()
            && ! $this->status->isTerminal()
            && $this->status !== OrderStatus::Delivered;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Order>  $query */
    public function scopeDrafts($query): void
    {
        $query->where('status', OrderStatus::Draft);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Order>  $query */
    public function scopeInWorkshop($query): void
    {
        $query->whereIn('status', array_map(
            fn (OrderStatus $s) => $s->value,
            OrderStatus::workshopStages(),
        ));
    }
}
