<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * One method. That is the whole surface an alternative WhatsApp provider — or
 * an SMS provider standing in for one — has to implement.
 */
interface WhatsAppDriver
{
    public function name(): string;

    public function isConfigured(): bool;

    /** @return bool True if the provider accepted the message for delivery. */
    public function send(WhatsAppMessage $message): bool;
}
