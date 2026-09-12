<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property string $gateway
 * @property string $status
 * @property int $amount_kobo
 * @property string $currency_code
 * @property string|null $gateway_reference
 * @property string $idempotency_key
 * @property string $kind
 * @property int|null $charged_minor
 * @property array<string,mixed>|null $gateway_payload
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read Order $order
 */
class Payment extends Model
{
    protected $guarded = [];

    protected $hidden = ['gateway_payload'];

    protected function casts(): array
    {
        return [
            'amount_kobo' => 'integer',
            'charged_minor' => 'integer',
            'fx_rate_used' => 'decimal:8',
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function amount(): Money
    {
        return Money::ofMinor((int) $this->amount_kobo);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }
}
