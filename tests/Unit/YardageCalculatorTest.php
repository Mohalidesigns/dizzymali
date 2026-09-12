<?php

declare(strict_types=1);

use App\Domain\Pricing\GarmentTypeSpec;
use App\Domain\Pricing\LineItemInput;
use App\Domain\Pricing\SelectedOption;
use App\Domain\Pricing\YardageCalculator;
use App\Domain\Pricing\YardageRule;

function agbadaSpec(array $rules = []): GarmentTypeSpec
{
    return new GarmentTypeSpec(
        id: 1,
        name: 'Agbada',
        baseSewingCostKobo: 45_000_00,
        defaultYardageHundredths: 500,
        leadTimeDays: 28,
        yardageRules: $rules ?: [
            new YardageRule('chest', 4_400, 25),
            new YardageRule('chest', 4_800, 50),
            new YardageRule('chest', 5_200, 75),
            new YardageRule('shirt_length', 5_800, 50),
        ],
    );
}

it('uses the base yardage when no rule is triggered', function () {
    $breakdown = (new YardageCalculator)->calculate(new LineItemInput(
        garmentType: agbadaSpec(),
        measurementsHundredths: ['chest' => 3_800, 'shirt_length' => 5_600],
    ));

    expect($breakdown->totalHundredths())->toBe(500)
        ->and($breakdown->sizeAdjustmentHundredths)->toBe(0);
});

it('applies only the highest matching tier for one measurement', function () {
    // A 53in chest clears all three chest tiers. It must add 0.75, not 1.50.
    $breakdown = (new YardageCalculator)->calculate(new LineItemInput(
        garmentType: agbadaSpec(),
        measurementsHundredths: ['chest' => 5_300],
    ));

    expect($breakdown->sizeAdjustmentHundredths)->toBe(75)
        ->and($breakdown->totalHundredths())->toBe(575);
});

it('adds rules on different measurements together', function () {
    $breakdown = (new YardageCalculator)->calculate(new LineItemInput(
        garmentType: agbadaSpec(),
        measurementsHundredths: ['chest' => 4_900, 'shirt_length' => 6_000],
    ));

    // 0.50 for the chest + 0.50 for the length.
    expect($breakdown->sizeAdjustmentHundredths)->toBe(100)
        ->and($breakdown->totalHundredths())->toBe(600);
});

it('treats a threshold as exclusive', function () {
    $breakdown = (new YardageCalculator)->calculate(new LineItemInput(
        garmentType: agbadaSpec(),
        measurementsHundredths: ['chest' => 4_400],
    ));

    expect($breakdown->sizeAdjustmentHundredths)->toBe(0);
});

it('ignores rules for measurements the customer did not give', function () {
    $breakdown = (new YardageCalculator)->calculate(new LineItemInput(
        garmentType: agbadaSpec(),
        measurementsHundredths: [],
    ));

    expect($breakdown->totalHundredths())->toBe(500);
});

it('adds yardage carried by selected options and by the customer', function () {
    $breakdown = (new YardageCalculator)->calculate(new LineItemInput(
        garmentType: agbadaSpec(),
        measurementsHundredths: ['chest' => 4_000],
        options: [new SelectedOption(1, 'Lining', 'Fully lined', 16_000_00, 100)],
        customerExtraYardsHundredths: 50,
    ));

    expect($breakdown->optionsHundredths)->toBe(100)
        ->and($breakdown->customerExtraHundredths)->toBe(50)
        ->and($breakdown->totalHundredths())->toBe(650);
});

it('never lets a negative customer extra reduce the yardage', function () {
    $breakdown = (new YardageCalculator)->calculate(new LineItemInput(
        garmentType: agbadaSpec(),
        customerExtraYardsHundredths: -500,
    ));

    expect($breakdown->totalHundredths())->toBe(500);
});

it('explains itself in language a customer can read', function () {
    $breakdown = (new YardageCalculator)->calculate(new LineItemInput(
        garmentType: agbadaSpec(),
        measurementsHundredths: ['chest' => 5_300],
    ));

    expect($breakdown->explanation('Agbada'))
        ->toBe('Agbada base 5 yd, +0.75 yd for your size');
});
