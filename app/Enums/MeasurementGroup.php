<?php

declare(strict_types=1);

namespace App\Enums;

enum MeasurementGroup: string
{
    case Top = 'top';
    case Trouser = 'trouser';

    public function label(): string
    {
        return match ($this) {
            self::Top => 'Top',
            self::Trouser => 'Trouser',
        };
    }
}
