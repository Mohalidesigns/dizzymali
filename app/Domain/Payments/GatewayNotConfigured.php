<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use RuntimeException;

/**
 * Thrown when a gateway is asked to do real work before its credentials exist.
 *
 * This is deliberately loud. A gateway that silently pretends to work is how a
 * customer ends up with a confirmed order nobody was paid for.
 */
class GatewayNotConfigured extends RuntimeException
{
    public static function for(string $gateway): self
    {
        return new self(sprintf(
            'The %s gateway has no credentials configured. Add them to .env before taking payments through it.',
            ucfirst($gateway),
        ));
    }
}
