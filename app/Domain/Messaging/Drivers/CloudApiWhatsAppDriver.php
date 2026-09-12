<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Drivers;

use App\Domain\Messaging\WhatsAppDriver;
use App\Domain\Messaging\WhatsAppMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta's WhatsApp Business Cloud API.
 *
 * Complete and ready. Inert until WHATSAPP_PHONE_NUMBER_ID and
 * WHATSAPP_ACCESS_TOKEN are set, at which point setting WHATSAPP_ENABLED=true
 * and WHATSAPP_DRIVER=cloud_api switches it on.
 *
 * Template names come from config, because Meta's approval process decides what
 * they are called and that can take days. Put the approved names in the .env.
 */
class CloudApiWhatsAppDriver implements WhatsAppDriver
{
    /** @param  array<string,string>  $templateNames */
    public function __construct(
        private readonly ?string $phoneNumberId,
        private readonly ?string $accessToken,
        private readonly array $templateNames = [],
        private readonly string $apiVersion = 'v21.0',
        private readonly string $defaultLocale = 'en',
    ) {}

    public function name(): string
    {
        return 'cloud_api';
    }

    public function isConfigured(): bool
    {
        return filled($this->phoneNumberId) && filled($this->accessToken);
    }

    public function send(WhatsAppMessage $message): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('WhatsApp Cloud API driver called without credentials — message not sent.', [
                'template' => $message->templateKey,
            ]);

            return false;
        }

        $templateName = $this->templateNames[$message->templateKey] ?? $message->templateKey;

        $response = Http::baseUrl("https://graph.facebook.com/{$this->apiVersion}")
            ->withToken((string) $this->accessToken)
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 300, throw: false)
            ->post("/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $message->normalisedTo(),
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => $message->locale ?? $this->defaultLocale],
                    'components' => $message->parameters === [] ? [] : [[
                        'type' => 'body',
                        'parameters' => array_map(
                            static fn (string $p) => ['type' => 'text', 'text' => $p],
                            $message->parameters,
                        ),
                    ]],
                ],
            ]);

        if ($response->failed()) {
            Log::error('WhatsApp Cloud API rejected a message.', [
                'template' => $templateName,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        }

        return true;
    }
}
