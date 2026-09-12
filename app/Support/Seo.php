<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Per-page metadata, shared into Inertia and rendered in the layout head.
 */
final class Seo
{
    /**
     * @param  array<string,mixed>|null  $structuredData  JSON-LD, already shaped
     * @return array<string,mixed>
     */
    public static function make(
        string $title,
        string $description,
        ?string $image = null,
        ?string $canonical = null,
        ?array $structuredData = null,
        bool $noindex = false,
    ): array {
        return [
            'title' => $title,
            'description' => self::truncate($description, 155),
            'image' => $image,
            'canonical' => $canonical ?? url()->current(),
            'noindex' => $noindex,
            'structured_data' => $structuredData,
        ];
    }

    /**
     * Schema.org Product for a garment type.
     *
     * "from" pricing, because the real number depends on the cloth chosen —
     * claiming an exact price we cannot honour is worse than none.
     *
     * @return array<string,mixed>
     */
    public static function garmentProduct(
        string $name,
        string $description,
        string $url,
        int $fromKobo,
        ?string $image = null,
    ): array {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $name,
            'description' => self::truncate($description, 300),
            'url' => $url,
            'image' => $image,
            'brand' => ['@type' => 'Brand', 'name' => 'DizzyMali'],
            'offers' => [
                '@type' => 'AggregateOffer',
                'priceCurrency' => 'NGN',
                'lowPrice' => number_format($fromKobo / 100, 2, '.', ''),
                'availability' => 'https://schema.org/MadeToOrder',
                'seller' => ['@type' => 'Organization', 'name' => 'DizzyMali'],
            ],
        ]);
    }

    /** @return array<string,mixed> */
    public static function organisation(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ClothingStore',
            'name' => 'DizzyMali',
            'description' => 'Made-to-measure Agbada, Kaftan, Jalabiya and Danshiki, cut in Nigeria and shipped worldwide.',
            'url' => url('/'),
            'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'NG'],
        ];
    }

    private static function truncate(string $value, int $length): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');

        return mb_strlen($clean) <= $length ? $clean : mb_substr($clean, 0, $length - 1).'…';
    }
}
