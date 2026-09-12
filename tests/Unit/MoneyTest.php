<?php

declare(strict_types=1);

use App\Support\Money;

it('adds and subtracts without drift', function () {
    $a = Money::ofMinor(15_000_00);
    $b = Money::ofMinor(45_000_00);

    expect($a->plus($b)->minor)->toBe(60_000_00)
        ->and($b->minus($a)->minor)->toBe(30_000_00);
});

it('refuses to mix currencies', function () {
    Money::ofMinor(100, 'NGN')->plus(Money::ofMinor(100, 'GBP'));
})->throws(InvalidArgumentException::class);

it('multiplies by yardage in hundredths with no float drift', function () {
    // 4.5 yards of N15,000/yd is exactly N67,500 — the classic float trap.
    $result = Money::ofMinor(15_000_00)->timesHundredths(450);

    expect($result->minor)->toBe(67_500_00);
});

it('rounds the final kobo half-up and never mid-calculation', function () {
    // 0.33 yards of N10,000.01 = N3,300.0033 -> 330000 kobo
    expect(Money::ofMinor(10_000_01)->timesHundredths(33)->minor)->toBe(330_000);

    // A value landing exactly on .5 kobo rounds up.
    expect(Money::ofMinor(1)->timesHundredths(50)->minor)->toBe(1);
    expect(Money::ofMinor(1)->timesHundredths(49)->minor)->toBe(0);
});

it('applies basis points exactly', function () {
    // 25% express surcharge on N45,000 sewing.
    expect(Money::ofMinor(45_000_00)->plusBasisPoints(2_500)->minor)->toBe(56_250_00);

    // 8% FX margin.
    expect(Money::ofMinor(10_000)->plusBasisPoints(800)->minor)->toBe(10_800);
});

it('takes a percentage in basis points', function () {
    expect(Money::ofMinor(400_000_00)->percentageBasisPoints(6_000)->minor)->toBe(240_000_00);
});

it('rounds display amounts up, never down', function () {
    expect(Money::ofMinor(18_001, 'GBP')->roundUpToNearest(100)->minor)->toBe(18_100)
        ->and(Money::ofMinor(18_000, 'GBP')->roundUpToNearest(100)->minor)->toBe(18_000)
        ->and(Money::ofMinor(18_099, 'GBP')->roundUpToNearest(100)->minor)->toBe(18_100);
});

it('converts currency through integer arithmetic', function () {
    // N312,250 at 0.000512 GBP per NGN = £159.872 -> 15987 pence
    $converted = Money::ofMinor(312_250_00)->convertTo('GBP', 51_200);

    expect($converted->minor)->toBe(15_987)
        ->and($converted->currency)->toBe('GBP');
});

it('clamps at zero rather than going negative', function () {
    expect(Money::ofMinor(100)->minus(Money::ofMinor(500))->atLeastZero()->minor)->toBe(0);
});

it('formats with a thousands separator and fixed decimals', function () {
    expect(Money::ofMinor(312_250_00)->format())->toBe('₦312,250.00')
        ->and(Money::ofMinor(15_987, 'GBP')->format(2, '£'))->toBe('£159.87');
});
