<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Messaging\WhatsAppMessage;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentReceivedNotification extends OrderNotification
{
    public function __construct(Order $order, public readonly Payment $payment)
    {
        parent::__construct($order);
    }

    public function key(): string
    {
        return 'payment_received';
    }

    public function title(): string
    {
        return 'Payment received';
    }

    public function body(): string
    {
        $amount = $this->payment->amount()->format();
        $balance = $this->order->balanceDue();

        return $balance->isZero()
            ? sprintf('We have received %s for order %s. Nothing further to pay.', $amount, $this->order->reference)
            : sprintf(
                'We have received %s for order %s. Balance of %s due before shipping.',
                $amount,
                $this->order->reference,
                $balance->format(),
            );
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payment received — {$this->order->reference}")
            ->greeting("Thank you, {$notifiable->name}.")
            ->line('We have received '.$this->payment->amount()->format().'.')
            ->when(! $this->order->balanceDue()->isZero(), fn (MailMessage $m) => $m->line(
                'Balance of '.$this->order->balanceDue()->format().' is due before your garment ships.',
            ))
            ->line('Your garment now joins the cutting queue. We will send photographs as it is made.')
            ->action('See your order', route('orders.show', $this->order))
            ->salutation('DizzyMali');
    }

    public function toWhatsApp(mixed $notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            to: (string) $notifiable->phone,
            templateKey: 'payment_received',
            parameters: [
                (string) $notifiable->name,
                $this->payment->amount()->format(),
                (string) $this->order->reference,
            ],
            preview: $this->body(),
        );
    }
}
