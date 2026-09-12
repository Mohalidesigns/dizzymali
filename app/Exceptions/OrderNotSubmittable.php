<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class OrderNotSubmittable extends RuntimeException
{
    /** @param  list<string>  $reasons */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct('This order is not ready to be submitted: '.implode(' ', $reasons));
    }
}
