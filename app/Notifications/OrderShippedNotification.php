<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Messaging\WhatsAppMessage;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Notifications\Messages\MailMessage;

class OrderShippedNotification extends OrderNotification
{
    public function __construct(Order $order, public readonly Shipment $shipment)
    {
        parent::__construct($order);
    }

    public function key(): string
    {
        return 'order_shipped';
    }

    public function title(): string
    {
        return 'On its way';
    }

    public function body(): string
    {
        return $this->shipment->tracking_number === null
            ? sprintf('Order %s has been handed to the courier.', $this->order->reference)
            : sprintf(
                'Order %s is with %s, tracking %s.',
                $this->order->reference,
                $this->shipment->carrier ?? 'the courier',
                $this->shipment->tracking_number,
            );
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Your garment is on its way — {$this->order->reference}")
            ->greeting("Hello {$notifiable->name},")
            ->line($this->body());

        if ($this->shipment->tracking_url !== null) {
            $mail->action('Track your parcel', $this->shipment->tracking_url);
        } else {
            $mail->action('See your order', route('orders.show', $this->order));
        }

        // Saying this up front is what stops a parcel being refused at the door.
        return $mail
            ->line('Customs duty, where it applies, is payable by the recipient on delivery.')
            ->salutation('DizzyMali');
    }

    public function toWhatsApp(mixed $notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            to: (string) $notifiable->phone,
            templateKey: 'order_shipped',
            parameters: [
                (string) $notifiable->name,
                (string) $this->order->reference,
                (string) ($this->shipment->tracking_number ?? 'pending'),
            ],
            preview: $this->body(),
        );
    }
}
