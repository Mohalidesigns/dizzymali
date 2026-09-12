<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Domain\Messaging\WhatsAppDriver;
use App\Domain\Messaging\WhatsAppMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * The Laravel notification channel for WhatsApp.
 *
 * Three gates, in order, each of which is a different situation and is logged
 * differently — because "we chose not to send", "the customer did not opt in"
 * and "the provider refused" are three different answers to "why didn't they
 * get a message?".
 */
class WhatsAppChannel
{
    public function __construct(private readonly WhatsAppDriver $driver) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        if (! (bool) config('notifications.channels.whatsapp.enabled', false)) {
            Log::info('WhatsApp is switched off — message not sent.', [
                'notification' => $notification::class,
            ]);

            return;
        }

        $to = $this->recipient($notifiable);

        if ($to === null) {
            return;
        }

        /** @var WhatsAppMessage|null $message */
        $message = $notification->toWhatsApp($notifiable);

        if ($message === null) {
            return;
        }

        $this->driver->send(new WhatsAppMessage(
            to: $to,
            templateKey: $message->templateKey,
            parameters: $message->parameters,
            locale: $message->locale,
            preview: $message->preview,
        ));
    }

    /** Consent is not optional: no number, or no opt-in, means no message. */
    private function recipient(mixed $notifiable): ?string
    {
        if (! is_object($notifiable)) {
            return null;
        }

        $phone = $notifiable->phone ?? null;
        $optedIn = (bool) ($notifiable->whatsapp_opt_in ?? false);

        if (! $optedIn || ! filled($phone)) {
            return null;
        }

        return (string) $phone;
    }
}
