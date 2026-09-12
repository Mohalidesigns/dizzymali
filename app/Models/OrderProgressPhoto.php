<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photograph of the customer's fabric being cut, or the garment on the
 * machine. For a customer 4,000 km away this is the single highest-value piece
 * of content the workshop produces.
 */
class OrderProgressPhoto extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_customer_visible' => 'boolean',
            'notified' => 'boolean',
        ];
    }

    /** Always routed through an authorisation check, never a public bucket. */
    public function url(): string
    {
        return route('progress-photos.show', $this);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
