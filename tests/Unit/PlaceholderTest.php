<?php

declare(strict_types=1);

use App\Domain\Media\Placeholder;

it('is deterministic — the same record always gets the same placeholder', function () {
    $first = Placeholder::svg('atiku-linen-navy', 'Atiku Linen — Navy');
    $second = Placeholder::svg('atiku-linen-navy', 'Atiku Linen — Navy');

    expect($first)->toBe($second);
});

it('gives different records different placeholders', function () {
    expect(Placeholder::svg('kano-cashmere', 'Kano Cashmere'))
        ->not->toBe(Placeholder::svg('shadda-cotton', 'Shadda Cotton'));
});

it('uses the fabric colour when there is one', function () {
    expect(Placeholder::svg('x', 'Navy linen', 400, 500, '#1E2A44'))
        ->toContain('#1E2A44');
});

it('escapes the label so a fabric name cannot inject markup', function () {
    $svg = Placeholder::svg('x', '<script>alert(1)</script>');

    expect($svg)->not->toContain('<script>')
        ->and($svg)->toContain('&lt;script&gt;');
});

it('produces a data URI an img tag can use directly', function () {
    expect(Placeholder::dataUri('x', 'Label'))->toStartWith('data:image/svg+xml;base64,');
});

/*
|--------------------------------------------------------------------------
| Contrast
|--------------------------------------------------------------------------
| CLAUDE.md is specific: ink on terracotta, never white. A naive brightness
| threshold gets this wrong, so the helper measures both ratios.
*/

it('puts ink on terracotta, not cream', function () {
    expect(Placeholder::readableInkOn('#E2620E'))->toBe('#14110F');
});

it('puts ink on gold', function () {
    expect(Placeholder::readableInkOn('#C9A227'))->toBe('#14110F');
});

it('puts cream on the dark grounds', function () {
    expect(Placeholder::readableInkOn('#1E2A44'))->toBe('#FAF6F0')
        ->and(Placeholder::readableInkOn('#6E1F2C'))->toBe('#FAF6F0');
});

it('puts ink on the light grounds', function () {
    expect(Placeholder::readableInkOn('#FAF6F0'))->toBe('#14110F')
        ->and(Placeholder::readableInkOn('#EFE7DC'))->toBe('#14110F');
});

it('always picks the option that actually passes AA at large text', function () {
    // 3:1 is the AA bar for large text; every palette colour must clear it.
    foreach (['#E2620E', '#C9A227', '#1E2A44', '#6E1F2C', '#FAF6F0', '#EFE7DC', '#5B6247'] as $hex) {
        $ink = Placeholder::readableInkOn($hex);
        expect(contrast($hex, $ink))->toBeGreaterThan(3.0, "{$ink} on {$hex}");
    }
});

function contrast(string $a, string $b): float
{
    $luminance = static function (string $hex): float {
        $channel = static function (int $v): float {
            $c = $v / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel((int) hexdec(substr($hex, 1, 2)))
            + 0.7152 * $channel((int) hexdec(substr($hex, 3, 2)))
            + 0.0722 * $channel((int) hexdec(substr($hex, 5, 2)));
    };

    $la = $luminance($a);
    $lb = $luminance($b);

    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}
