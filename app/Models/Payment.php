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
