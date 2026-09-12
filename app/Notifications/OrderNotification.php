<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Shared behaviour for every order notification.
 *
 * Which channels fire is config, not code (`config/notifications.matrix`), so
 * the admin can turn a channel off for one notification without a deploy. Every
 * one of these is queued — rule 6: side effects never run in the request cycle.
 */
abstract class OrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Order $order)
    {
        $this->onQueue('notifications');
    }

    /** The key into config/notifications.php — matrix and WhatsApp templates. */
    abstract public function key(): string;

    /** @return list<string> */
    public function via(mixed $notifiable): array
    {
        /** @var list<string> $configured */
        $configured = (array) config('notifications.matrix.'.$this->key(), ['mail']);

        return collect($configured)
            ->filter(fn (string $channel) => (bool) config("notifications.channels.{$channel}.enabled", true))
            ->map(fn (string $channel) => $channel === 'whatsapp' ? WhatsAppChannel::class : $channel)
            ->values()
            ->all();
    }

    /** @return array<string,mixed> The in-app notification payload. */
    public function toArray(mixed $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'reference' => $this->order->reference,
            'stage' => $this->order->status->customerStage()->value,
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => route('orders.show', $this->order),
        ];
    }

    abstract public function title(): string;

    abstract public function body(): string;
}
