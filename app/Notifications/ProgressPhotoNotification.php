<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Messaging\WhatsAppMessage;
use App\Models\Order;
use App\Models\OrderProgressPhoto;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A photograph of the customer's own cloth on the cutting table.
 *
 * For someone 4,000 km away this is the highest-value thing the workshop
 * produces that is not the garment itself.
 */
class ProgressPhotoNotification extends OrderNotification
{
    public function __construct(Order $order, public readonly OrderProgressPhoto $photo)
    {
        parent::__construct($order);
    }

    public function key(): string
    {
        return 'progress_photo';
    }

    public function title(): string
    {
        return 'A photo from the workshop';
    }

    public function body(): string
    {
        return $this->photo->caption
            ?? sprintf('There is a new photograph of order %s.', $this->order->reference);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("A photo of your garment — {$this->order->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line($this->body())
            ->action('See the photo', route('orders.show', $this->order))
            ->salutation('DizzyMali');
    }

    public function toWhatsApp(mixed $notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            to: (string) $notifiable->phone,
            templateKey: 'progress_photo',
            parameters: [(string) $notifiable->name, (string) $this->order->reference],
            preview: $this->body(),
        );
    }
}
