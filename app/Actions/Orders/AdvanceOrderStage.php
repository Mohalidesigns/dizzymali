<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Domain\Orders\OrderStateMachine;
use App\Enums\OrderStatus;
use App\Models\FabricVariant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Moves an order along the workshop pipeline and fires the side effects that
 * belong to each stage. Every side effect is queued; none runs inline.
 */
class AdvanceOrderStage
{
    public function __construct(private readonly OrderStateMachine $states) {}

    public function handle(Order $order, OrderStatus $to, User $actor, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $note) {
            $order = $this->states->transition($order, $to, $actor, $note);

            if ($to === OrderStatus::FabricSourced) {
                $this->consumeReservedFabric($order);
            }

            if (in_array($to, [OrderStatus::Cancelled, OrderStatus::Refunded], true)) {
                $this->releaseReservedFabric($order);
            }

            return $order;
        });
    }

    /** Reserved yards become consumed yards once the bolt is actually cut. */
    private function consumeReservedFabric(Order $order): void
    {
        foreach ($order->items()->with('fabricVariant')->get() as $item) {
            $variant = $item->fabricVariant;

            if ($variant === null) {
                continue;
            }

            $yards = (float) $item->yards_required * (int) $item->quantity;

            FabricVariant::query()->whereKey($variant->id)->update([
                'stock_yards' => DB::raw('GREATEST(stock_yards - '.$yards.', 0)'),
                'reserved_yards' => DB::raw('GREATEST(reserved_yards - '.$yards.', 0)'),
            ]);
        }
    }

    private function releaseReservedFabric(Order $order): void
    {
        foreach ($order->items()->with('fabricVariant')->get() as $item) {
            $variant = $item->fabricVariant;

            if ($variant === null) {
                continue;
            }

            $yards = (float) $item->yards_required * (int) $item->quantity;

            FabricVariant::query()->whereKey($variant->id)->update([
                'reserved_yards' => DB::raw('GREATEST(reserved_yards - '.$yards.', 0)'),
            ]);
        }
    }
}
