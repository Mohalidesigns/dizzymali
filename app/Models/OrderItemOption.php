<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_item_id
 * @property int|null $garment_option_id
 * @property string $group_name
 * @property string $option_name
 * @property int $surcharge_kobo
 * @property string $additional_yards
 */
class OrderItemOption extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'surcharge_kobo' => 'integer',
            'additional_yards' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
