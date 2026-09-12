<?php

declare(strict_types=1);

use App\Domain\Pricing\FabricVariantSpec;
use App\Domain\Pricing\GarmentTypeSpec;
use App\Domain\Pricing\LineItemInput;
use App\Domain\Pricing\QuoteCalculator;
use App\Domain\Pricing\QuoteContext;
use App\Domain\Pricing\SelectedOption;
use App\Domain\Pricing\YardageRule;
use App\Support\Money;

function linen(): FabricVariantSpec
{
    return new FabricVariantSpec(1, 'ATIKU-NAVY', 'Atiku Linen — Navy', 15_000_00, 26_000);
}

function agbada(): GarmentTypeSpec
{
    return new GarmentTypeSpec(
        id: 1,
        name: 'Agbada',
        baseSewingCostKobo: 45_000_00,
        defaultYardageHundredths: 450,
        leadTimeDays: 28,
        baseWeightGrams: 900,
        gramsPerYard: 240,
        yardageRules: [new YardageRule('chest', 4_800, 50)],
    );
}

/*
|--------------------------------------------------------------------------
| The golden test case
|--------------------------------------------------------------------------
| Agbada, 4.5 yards of N15,000/yd linen, N45,000 sewing, 25% express
| surcharge, N28,000 shipping to the UK, 8% FX margin, displayed in GBP.
|
| Every pricing change must keep this green. If it goes red, either the
| change is wrong or this fixture needs a deliberate, reviewed update — it
| is never "just a test to fix".
*/
it('prices the golden Agbada exactly', function () {
    $quote = (new QuoteCalculator)->calculate(
        [new LineItemInput(garmentType: agbada(), fabricVariant: linen(), quantity: 1)],
        new QuoteContext(
            shipping: Money::ofMinor(28_000_00),
            discount: Money::zero(),
            tax: Money::zero(),
            isExpress: true,
            expressSurchargeBasisPoints: 2_500,
            displayCurrency: 'GBP',
            fxRate1e8: 51_200,
            fxMarginBasisPoints: 800,
            displayRoundingMinor: 100,
        ),
    );

    // 4.5 yd x N15,000 = N67,500
    expect($quote->fabricTotal->minor)->toBe(67_500_00)
        // N45,000 + 25% = N56,250
        ->and($quote->sewingTotal->minor)->toBe(56_250_00)
        ->and($quote->subtotal->minor)->toBe(123_750_00)
        ->and($quote->shipping->minor)->toBe(28_000_00)
        // N151,750 all in
        ->and($quote->total->minor)->toBe(151_750_00)
        // x 0.000512 = GBP 77.696 -> 7770 pence, +8% = 8392, rounded up to GBP 84.00
        ->and($quote->displayTotalMinor)->toBe(8_400)
        // 900g + 4.5 yd x 240g
        ->and($quote->totalWeightGrams)->toBe(1_980)
        ->and($quote->leadTimeDays)->toBe(28);
});

it('adds yardage for a larger chest and charges for the extra cloth', function () {
    $quote = (new QuoteCalculator)->calculate(
        [new LineItemInput(
            garmentType: agbada(),
            fabricVariant: linen(),
            measurementsHundredths: ['chest' => 5_200],
        )],
        QuoteContext::plain(),
    );

    // 5.0 yd rather than 4.5.
    expect($quote->items[0]->yardage->totalHundredths())->toBe(500)
        ->and($quote->fabricTotal->minor)->toBe(75_000_00)
        ->and($quote->total->minor)->toBe(120_000_00);
});

it('multiplies the whole line by quantity', function () {
    $quote = (new QuoteCalculator)->calculate(
        [new LineItemInput(garmentType: agbada(), fabricVariant: linen(), quantity: 3)],
        QuoteContext::plain(),
    );

    expect($quote->subtotal->minor)->toBe(337_500_00)
        ->and($quote->totalWeightGrams)->toBe(5_940);
});

it('charges option surcharges and lets options add cloth', function () {
    $quote = (new QuoteCalculator)->calculate(
        [new LineItemInput(
            garmentType: agbada(),
            fabricVariant: linen(),
            options: [
                new SelectedOption(1, 'Embroidery', 'Standard', 18_000_00),
                new SelectedOption(2, 'Lining', 'Fully lined', 16_000_00, 100, 3),
            ],
        )],
        QuoteContext::plain(),
    );

    // 4.5 base + 1.0 from the lining = 5.5 yd.
    expect($quote->items[0]->yardage->totalHundredths())->toBe(550)
        ->and($quote->fabricTotal->minor)->toBe(82_500_00)
        ->and($quote->optionsTotal->minor)->toBe(34_000_00)
        ->and($quote->leadTimeDays)->toBe(31);
});

it('stays in naira when no FX rate is supplied', function () {
    $quote = (new QuoteCalculator)->calculate(
        [new LineItemInput(garmentType: agbada(), fabricVariant: linen())],
        QuoteContext::plain(),
    );

    expect($quote->displayCurrency)->toBe('NGN')
        ->and($quote->displayTotalMinor)->toBe($quote->total->minor)
        ->and($quote->fxRate1e8)->toBeNull();
});

it('never lets a discount push the total below zero', function () {
    $quote = (new QuoteCalculator)->calculate(
        [new LineItemInput(garmentType: agbada(), fabricVariant: linen())],
        new QuoteContext(
            shipping: Money::zero(),
            discount: Money::ofMinor(999_999_00),
            tax: Money::zero(),
        ),
    );

    expect($quote->total->minor)->toBe(0);
});

it('prices a garment with no fabric chosen as sewing only', function () {
    $quote = (new QuoteCalculator)->calculate(
        [new LineItemInput(garmentType: agbada())],
        QuoteContext::plain(),
    );

    expect($quote->fabricTotal->minor)->toBe(0)
        ->and($quote->total->minor)->toBe(45_000_00);
});

it('is deterministic — the same inputs always give the same total', function () {
    $run = fn () => (new QuoteCalculator)->calculate(
        [new LineItemInput(
            garmentType: agbada(),
            fabricVariant: linen(),
            quantity: 2,
            measurementsHundredths: ['chest' => 4_900],
            options: [new SelectedOption(1, 'Embroidery', 'Heavy', 45_000_00, 25)],
            customerExtraYardsHundredths: 75,
        )],
        new QuoteContext(
            shipping: Money::ofMinor(42_000_00),
            discount: Money::ofMinor(5_000_00),
            tax: Money::zero(),
            isExpress: true,
            expressSurchargeBasisPoints: 2_500,
            displayCurrency: 'USD',
            fxRate1e8: 65_000,
            fxMarginBasisPoints: 800,
            displayRoundingMinor: 100,
        ),
    );

    expect($run()->total->minor)->toBe($run()->total->minor)
        ->and($run()->displayTotalMinor)->toBe($run()->displayTotalMinor);
});
