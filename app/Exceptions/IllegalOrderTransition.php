<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OrderStatus;
use RuntimeException;

class IllegalOrderTransition extends RuntimeException
{
    public static function between(OrderStatus $from, OrderStatus $to): self
    {
        return new self(sprintf(
            'An order cannot move from "%s" to "%s".',
            $from->label(),
            $to->label(),
        ));
    }
}
