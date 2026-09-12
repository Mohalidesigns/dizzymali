<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/** Where to send the customer, and what the gateway called this attempt. */
final readonly class ChargeSession
{
    public function __construct(
        public string $gateway,
        public string $gatewayReference,
        public string $redirectUrl,
        /** @var array<string,mixed> */
        public array $raw = [],
    ) {}
}
