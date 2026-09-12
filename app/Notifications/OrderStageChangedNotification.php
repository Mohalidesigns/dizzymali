<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Messaging\WhatsAppMessage;
use App\Enums\CustomerStage;
use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;

class OrderStageChangedNotification extends OrderNotification
{
    public function __construct(Order $order, public readonly CustomerStage $stage)
    {
        parent::__construct($order);
    }

    public function key(): string
    {
        return 'stage_changed';
    }

    public function title(): string
    {
        return $this->stage->label();
    }

    public function body(): string
    {
        return $this->stage->description();
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->order->reference} — {$this->stage->label()}")
            ->greeting("Hello {$notifiable->name},")
            ->line($this->stage->description())
            ->action('Follow your order', route('orders.show', $this->order))
            ->salutation('DizzyMali');
    }

    public function toWhatsApp(mixed $notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            to: (string) $notifiable->phone,
            templateKey: 'stage_changed',
            parameters: [
                (string) $this->order->reference,
                $this->stage->label(),
                $this->stage->description(),
            ],
            preview: $this->body(),
        );
    }
}
