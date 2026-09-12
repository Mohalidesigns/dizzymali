<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Money;

/**
 * Money crosses the wire as integer minor units plus a pre-formatted string.
 *
 * The frontend never does arithmetic on money. It displays what the server
 * calculated, which is the only way the number on the screen and the number
 * charged can be guaranteed to match.
 */
final class MoneyResource
{
    /** @return array{minor:int,currency:string,formatted:string} */
    public static function make(int $minor, string $currency = 'NGN', ?string $symbol = null, int $decimals = 2): array
    {
        $symbol ??= match (strtoupper($currency)) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => strtoupper($currency).' ',
        };

        return [
            'minor' => $minor,
            'currency' => strtoupper($currency),
            'formatted' => Money::ofMinor($minor, $currency)->format($decimals, $symbol),
        ];
    }
}
