<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Enums\OrderStatus;
use App\Events\OrderStageChanged;
use App\Exceptions\IllegalOrderTransition;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only place an order's status is allowed to change.
 *
 * Nothing else in the application writes `orders.status`. Every transition is
 * validated against the enum's allowed set and recorded as an event, which
 * gives us the customer's timeline and our audit trail from the same rows.
 */
class OrderStateMachine
{
    public function canTransition(Order $order, OrderStatus $to): bool
    {
        return $order->status->canTransitionTo($to);
    }

    /**
     * @throws IllegalOrderTransition
     */
    public function transition(
        Order $order,
        OrderStatus $to,
        ?User $actor = null,
        ?string $note = null,
        bool $customerVisible = true,
    ): Order {
        $from = $order->status;

        if ($from === $to) {
            return $order;
        }

        if (! $from->canTransitionTo($to)) {
            throw IllegalOrderTransition::between($from, $to);
        }

        return DB::transaction(function () use ($order, $from, $to, $actor, $note, $customerVisible) {
            $order->status = $to;

            match ($to) {
                OrderStatus::Submitted => $order->submitted_at ??= now(),
                OrderStatus::Paid => $order->placed_at ??= now(),
                OrderStatus::Delivered => $order->completed_at ??= now(),
                OrderStatus::Cancelled => $order->cancelled_at = now(),
                default => null,
            };

            $order->save();

            OrderStatusEvent::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => $actor?->id,
                'note' => $note,
                'is_customer_visible' => $customerVisible,
            ]);

            $order->refresh();

            // Queued listeners do the telling. Nothing that notifies a customer
            // runs inside the request that moved the order.
            if ($customerVisible) {
                OrderStageChanged::dispatch($order, $from, $to);
            }

            return $order;
        });
    }

    /** The next stage in the happy path, for the admin's one-click advance. */
    public function nextStage(Order $order): ?OrderStatus
    {
        $allowed = $order->status->allowedTransitions();

        foreach ($allowed as $candidate) {
            if (! in_array($candidate, [
                OrderStatus::OnHold, OrderStatus::Cancelled, OrderStatus::Refunded,
            ], true)) {
                return $candidate;
            }
        }

        return null;
    }
}
