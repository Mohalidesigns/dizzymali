<?php

declare(strict_types=1);

namespace App\Domain\Payments;

final readonly class WebhookEvent
{
    public function __construct(
        public string $gateway,
        public string $type,
        public ?string $gatewayReference,
        public ?int $amountMinor,
        public ?string $currency,
        /** @var array<string,mixed> */
        public array $raw = [],
    ) {}

    public function isSuccessfulCharge(): bool
    {
        return in_array($this->type, ['charge.success', 'charge.completed'], true);
    }
}
