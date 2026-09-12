<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Stand-in imagery, until the photography shoot happens.
 *
 * The storefront's job is to make the clothes look extraordinary, and it cannot
 * do that yet — there are no photographs. What it can do is not look broken.
 * Every catalogue image without a real upload falls back to a generated SVG
 * built from the record's own name and the DizzyMali palette: deterministic, so
 * the same fabric is the same swatch on every page load and every device, and
 * inline, so it costs no request.
 *
 * Uploading a real image replaces it. There is nothing to undo.
 */
final class Placeholder
{
    /** The palette, ordered so adjacent catalogue items do not collide. */
    private const PALETTE = [
        ['#EFE7DC', '#14110F'],
        ['#E2D8CB', '#14110F'],
        ['#1E2A44', '#FAF6F0'],
        ['#5B6247', '#FAF6F0'],
        ['#6E1F2C', '#FAF6F0'],
        ['#C9A227', '#14110F'],
        ['#E2620E', '#14110F'],
        ['#2B3A67', '#FAF6F0'],
    ];

    /**
     * A data URI an <img> can use directly.
     *
     * @param  string  $seed  Stable identity — a slug or SKU, never a random value.
     * @param  string  $label  Shown on the placeholder so staff can see what is missing.
     */
    public static function dataUri(
        string $seed,
        string $label,
        int $width = 800,
        int $height = 1000,
        ?string $hex = null,
        bool $badge = true,
    ): string {
        return 'data:image/svg+xml;base64,'.base64_encode(
            self::svg($seed, $label, $width, $height, $hex, $badge),
        );
    }

    public static function svg(
        string $seed,
        string $label,
        int $width = 800,
        int $height = 1000,
        ?string $hex = null,
        bool $badge = true,
    ): string {
        [$background, $ink] = self::colours($seed, $hex);
        $safeLabel = htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $id = 'g'.substr(md5($seed), 0, 8);
        $fontSize = max(16, (int) round($width / 18));

        // A soft diagonal wash rather than a flat block: at thumbnail size a
        // flat rectangle reads as a failed image, a gradient reads as a design.
        // A soft diagonal wash rather than a flat block: at thumbnail size a
        // flat rectangle reads as a failed image, a gradient reads as a design.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" '
            .'width="'.$width.'" height="'.$height.'" role="img" aria-label="'.$safeLabel.'">'
            .'<defs><linearGradient id="'.$id.'" x1="0" y1="0" x2="1" y2="1">'
            .'<stop offset="0%" stop-color="'.$background.'"/>'
            .'<stop offset="100%" stop-color="'.$background.'" stop-opacity="0.72"/>'
            .'</linearGradient></defs>'
            .'<rect width="'.$width.'" height="'.$height.'" fill="url(#'.$id.')"/>'
            .'<text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle" '
            .'font-family="Bodoni Moda, Georgia, serif" font-size="'.$fontSize.'" '
            .'fill="'.$ink.'" opacity="0.82">'.$safeLabel.'</text>';

        if ($badge) {
            $badgeSize = max(9, (int) round($width / 48));

            $svg .= '<text x="50%" y="'.$height.'" dy="-18" text-anchor="middle" '
                .'font-family="Jost, Helvetica, sans-serif" font-size="'.$badgeSize.'" '
                .'letter-spacing="2" fill="'.$ink.'" opacity="0.5">PHOTOGRAPH TO COME</text>';
        }

        return $svg.'</svg>';
    }

    /** @return array{0:string,1:string} background, ink */
    public static function colours(string $seed, ?string $hex = null): array
    {
        if ($hex !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1) {
            return [$hex, self::readableInkOn($hex)];
        }

        $index = hexdec(substr(md5($seed), 0, 4)) % count(self::PALETTE);

        return self::PALETTE[$index];
    }

    /**
     * Ink or cream on this background, whichever actually wins on contrast.
     *
     * Computed, not guessed at with a brightness threshold. A threshold puts
     * cream on our terracotta, which is 3.46:1 and fails AA; ink on terracotta
     * is 5.35:1 and is what CLAUDE.md requires. So we measure both and take
     * the better one, which gets that case right without a special rule.
     */
    public static function readableInkOn(string $hex): string
    {
        $background = self::relativeLuminance($hex);

        $againstInk = self::contrastRatio($background, self::relativeLuminance('#14110F'));
        $againstCream = self::contrastRatio($background, self::relativeLuminance('#FAF6F0'));

        return $againstInk >= $againstCream ? '#14110F' : '#FAF6F0';
    }

    /** WCAG 2.1 relative luminance. */
    private static function relativeLuminance(string $hex): float
    {
        $channel = static function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel((int) hexdec(substr($hex, 1, 2)))
            + 0.7152 * $channel((int) hexdec(substr($hex, 3, 2)))
            + 0.0722 * $channel((int) hexdec(substr($hex, 5, 2)));
    }

    private static function contrastRatio(float $a, float $b): float
    {
        $lighter = max($a, $b);
        $darker = min($a, $b);

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}
