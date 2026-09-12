<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Fabric;
use App\Models\GarmentType;
use Illuminate\Http\Response;

/**
 * Sitemap and robots.
 *
 * Diaspora discovery is search-led — someone in Manchester looking for "bespoke
 * agbada" will not already know the brand name. The crawlable surface is the
 * catalogue; everything behind authentication is explicitly kept out.
 */
class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('home'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => route('garments.index'), 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => route('fabrics.index'), 'changefreq' => 'daily', 'priority' => '0.8'],
        ];

        foreach (GarmentType::query()->where('is_active', true)->get() as $garment) {
            $urls[] = [
                'loc' => route('garments.show', $garment->slug),
                'lastmod' => $garment->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        foreach (Fabric::query()->where('is_active', true)->get() as $fabric) {
            $urls[] = [
                'loc' => route('fabrics.index', ['material' => $fabric->material?->slug]),
                'lastmod' => $fabric->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url>'."\n".'    <loc>'.e($url['loc']).'</loc>'."\n";

            if (isset($url['lastmod'])) {
                $xml .= '    <lastmod>'.e($url['lastmod']).'</lastmod>'."\n";
            }

            $xml .= '    <changefreq>'.$url['changefreq'].'</changefreq>'."\n"
                .'    <priority>'.$url['priority'].'</priority>'."\n"
                .'  </url>'."\n";
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            // Everything below is either private or pointless to index.
            'Disallow: /admin',
            'Disallow: /order',
            'Disallow: /orders',
            'Disallow: /measurements',
            'Disallow: /checkout',
            'Disallow: /inspirations',
            'Disallow: /webhooks',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain']);
    }
}
