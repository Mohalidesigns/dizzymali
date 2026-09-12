<?php

declare(strict_types=1);

namespace App\Enums;

/** What the customer's timeline shows. Five friendly stages, plus two exits. */
enum CustomerStage: string
{
    case Received = 'received';
    case FabricAndCutting = 'fabric_and_cutting';
    case Sewing = 'sewing';
    case QualityCheck = 'quality_check';
    case OnItsWay = 'on_its_way';
    case OnHold = 'on_hold';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Received',
            self::FabricAndCutting => 'Fabric & cutting',
            self::Sewing => 'Sewing',
            self::QualityCheck => 'Quality check',
            self::OnItsWay => 'On its way',
            self::OnHold => 'On hold',
            self::Cancelled => 'Cancelled',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Received => 'We have your order and your measurements.',
            self::FabricAndCutting => 'Your fabric is sourced and being cut to your measurements.',
            self::Sewing => 'Your garment is on the machine.',
            self::QualityCheck => 'Finishing, pressing and a final check against your measurements.',
            self::OnItsWay => 'Packed and handed to the courier.',
            self::OnHold => 'Paused — we will be in touch.',
            self::Cancelled => 'This order was cancelled.',
        };
    }

    /** @return list<self> The five stages rendered on the timeline, in order. */
    public static function timeline(): array
    {
        return [self::Received, self::FabricAndCutting, self::Sewing, self::QualityCheck, self::OnItsWay];
    }

    public function position(): int
    {
        $index = array_search($this, self::timeline(), true);

        return $index === false ? 0 : $index + 1;
    }
}
