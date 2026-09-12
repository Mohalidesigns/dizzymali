<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $order_item_id
 * @property string $disk
 * @property string $path
 * @property string|null $original_name
 * @property-read OrderItem $orderItem
 */
class OrderItemInspiration extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Signed, short-lived, never a public bucket URL. These are photographs a
     * customer uploaded of clothes they want; they are not public assets.
     */
    public function temporaryUrl(int $minutes = 15): string
    {
        try {
            // S3 signs a short-lived URL. The local disk cannot, and throws —
            // in that case we fall back to a route that authorises and streams.
            return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes($minutes));
        } catch (\Throwable) {
            return route('inspirations.show', $this);
        }
    }
}
