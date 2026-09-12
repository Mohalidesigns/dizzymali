<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CmsBlock;
use Illuminate\Database\Seeder;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            [
                'type' => 'hero',
                'placement' => 'homepage',
                'title' => 'Commissioned, not bought.',
                'payload' => [
                    'eyebrow' => 'DizzyMali — Kano & Lagos',
                    'body' => 'Agbada, Kaftan, Jalabiya and Danshiki, cut to your measurements and sent anywhere in the world.',
                    'cta_label' => 'Start your garment',
                    'cta_href' => '/order',
                    'secondary_label' => 'See the cloth',
                    'secondary_href' => '/fabrics',
                ],
                'sort_order' => 0,
            ],
            [
                'type' => 'carousel',
                'placement' => 'homepage',
                'title' => 'Four garments',
                'payload' => ['source' => 'garment_types'],
                'sort_order' => 1,
            ],
            [
                'type' => 'testimonial',
                'placement' => 'homepage',
                'title' => 'From the diaspora',
                'payload' => [
                    'quotes' => [
                        ['body' => 'They sent me a photograph of my cloth on the cutting table. I have never had that from a tailor in London.', 'author' => 'Ibrahim S.', 'location' => 'Manchester'],
                        ['body' => 'Fit was right first time from measurements I took myself at home.', 'author' => 'Yusuf B.', 'location' => 'Houston'],
                    ],
                ],
                'sort_order' => 2,
            ],
        ];

        foreach ($blocks as $block) {
            CmsBlock::updateOrCreate(
                ['type' => $block['type'], 'placement' => $block['placement'], 'title' => $block['title']],
                $block + ['is_active' => true],
            );
        }
    }
}
