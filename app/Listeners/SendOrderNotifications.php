<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Events\OrderShipped;
use App\Events\OrderStageChanged;
use App\Events\OrderSubmitted;
use App\Events\ProgressPhotoAdded;
use App\Models\Order;
use App\Notifications\OrderShippedNotification;
use App\Notifications\OrderStageChangedNotification;
use App\Notifications\OrderSubmittedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\ProgressPhotoNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Everything that tells a customer something happened.
 *
 * Queued, per rule 6. A failed WhatsApp send must never be able to fail the
 * request that advanced the order.
 */
class SendOrderNotifications implements ShouldQueue
{
    public string $queue = 'notifications';

    public function handleSubmitted(OrderSubmitted $event): void
    {
        $this->notify($event->order, new OrderSubmittedNotification($event->order));
    }

    public function handlePaid(OrderPaid $event): void
    {
        $this->notify($event->order, new PaymentReceivedNotification($event->order, $event->payment));
    }

    public function handleStageChanged(OrderStageChanged $event): void
    {
        $from = $event->from->customerStage();
        $to = $event->to->customerStage();

        // The customer sees five stages, not sixteen. Moving from "fabric
        // sourced" to "cutting" is the same stage to them, so it is not a
        // message — nobody wants four texts about one garment being cut.
        if ($from === $to) {
            return;
        }

        $this->notify($event->order, new OrderStageChangedNotification($event->order, $to));
    }

    public function handleProgressPhoto(ProgressPhotoAdded $event): void
    {
        $this->notify($event->order, new ProgressPhotoNotification($event->order, $event->photo));
    }

    public function handleShipped(OrderShipped $event): void
    {
        $this->notify($event->order, new OrderShippedNotification($event->order, $event->shipment));
    }

    private function notify(Order $order, mixed $notification): void
    {
        $order->loadMissing('user');

        $order->user?->notify($notification);
    }
}
