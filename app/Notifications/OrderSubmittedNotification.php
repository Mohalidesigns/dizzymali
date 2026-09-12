<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Messaging\WhatsAppMessage;
use Illuminate\Notifications\Messages\MailMessage;

class OrderSubmittedNotification extends OrderNotification
{
    public function key(): string
    {
        return 'order_submitted';
    }

    public function title(): string
    {
        return 'We have your order';
    }

    public function body(): string
    {
        return sprintf(
            'Order %s is with the workshop. We will confirm your measurements before anything is cut.',
            $this->order->reference,
        );
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $item = $this->order->items->first();
        $garment = $item?->garmentType === null ? 'your garment' : (string) $item->garmentType->name;

        return (new MailMessage)
            ->subject("Order {$this->order->reference} — we have it")
            ->greeting("Thank you, {$notifiable->name}.")
            ->line("Your {$garment} is with the workshop.")
            ->line('Total: '.$this->order->total()->format())
            ->when($this->order->promised_at !== null, fn (MailMessage $m) => $m->line(
                'Expected to be finished by '.$this->order->promised_at->format('j F Y').'.',
            ))
            ->line('We check every measurement against your order before cutting. If anything looks off, we will ask you first.')
            ->action('Follow your order', route('orders.show', $this->order))
            ->salutation('DizzyMali');
    }

    public function toWhatsApp(mixed $notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            to: (string) $notifiable->phone,
            templateKey: 'order_submitted',
            parameters: [
                (string) $notifiable->name,
                (string) $this->order->reference,
                $this->order->total()->format(),
            ],
            preview: $this->body(),
        );
    }
}
