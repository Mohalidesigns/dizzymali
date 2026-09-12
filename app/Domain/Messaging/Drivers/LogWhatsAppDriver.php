<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Drivers;

use App\Domain\Messaging\WhatsAppDriver;
use App\Domain\Messaging\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

/**
 * What runs until the Cloud API credentials arrive.
 *
 * It writes the exact message that would have gone out to the log, which means
 * the whole notification path — events, listeners, queue, templates, phone
 * number normalisation — is exercised and testable today. Switching on the real
 * provider is a .env change, not a code change.
 */
class LogWhatsAppDriver implements WhatsAppDriver
{
    public function __construct(private readonly string $channel = 'stack') {}

    public function name(): string
    {
        return 'log';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(WhatsAppMessage $message): bool
    {
        Log::channel($this->channel)->info('WhatsApp (not sent — no provider configured)', [
            'to' => $message->normalisedTo(),
            'template' => $message->templateKey,
            'parameters' => $message->parameters,
            'preview' => $message->preview,
        ]);

        return true;
    }
}
