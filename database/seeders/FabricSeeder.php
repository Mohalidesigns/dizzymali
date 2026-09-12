<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Fabric;
use App\Models\FabricMaterial;
use App\Models\FabricVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Four materials, six fabrics, fourteen variants.
 *
 * Price lives on the variant, never the fabric — the navy linen and the ivory
 * linen off the same bolt rarely cost the same.
 */
class FabricSeeder extends Seeder
{
    public function run(): void
    {
        $materials = [
            ['cashmere', 'Cashmere', 'Soft, warm, and the most expensive thing on the rail. Best for cooler evenings and for a garment meant to last a decade.'],
            ['linen', 'Linen', 'Breathes better than anything else here. Creases, and is supposed to.'],
            ['cotton', 'Cotton', 'The everyday workhorse. Takes embroidery well and washes without drama.'],
            ['nylon', 'Nylon', 'Crisp, holds a sharp line, and travels without creasing.'],
        ];

        $materialModels = [];

        foreach ($materials as $i => [$slug, $name, $description]) {
            $materialModels[$slug] = FabricMaterial::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }

        $fabrics = [
            [
                'material' => 'cashmere',
                'name' => 'Kano Cashmere',
                'origin' => 'Kano, Nigeria',
                'gsm' => 280,
                'width_inches' => 58,
                'care' => 'Dry clean only. Store folded, never on a wire hanger.',
                'drape' => 'Heavy, falls in deep folds. Exceptional on an Agbada.',
                'variants' => [
                    ['Midnight', '#14110F', null, 32_000_00, 120],
                    ['Camel', '#B4886B', null, 32_000_00, 84],
                    ['Ivory', '#F2E9DC', null, 34_000_00, 60],
                ],
            ],
            [
                'material' => 'linen',
                'name' => 'Atiku Linen',
                'origin' => 'Imported, finished in Lagos',
                'gsm' => 190,
                'width_inches' => 60,
                'care' => 'Machine wash cold, line dry, press while damp.',
                'drape' => 'Crisp with a soft break. The default choice for Kaftan and Jalabiya.',
                'variants' => [
                    ['Navy', '#1E2A44', null, 15_000_00, 260],
                    ['Sand', '#DCCBB4', null, 15_000_00, 190],
                    ['Olive', '#5B6247', null, 16_000_00, 140],
                    ['White', '#FBFAF7', null, 14_500_00, 300],
                ],
            ],
            [
                'material' => 'linen',
                'name' => 'Guinea Brocade',
                'origin' => 'Austria, finished in Kano',
                'gsm' => 220,
                'width_inches' => 56,
                'care' => 'Hand wash, do not wring. Press on the reverse.',
                'drape' => 'Structured with a low sheen. Traditional choice for ceremony.',
                'variants' => [
                    ['Royal Blue', '#22437E', 'Damask', 21_000_00, 96],
                    ['Wine', '#6E1F2C', 'Damask', 21_000_00, 72],
                ],
            ],
            [
                'material' => 'cotton',
                'name' => 'Ankara Wax Cotton',
                'origin' => 'Abeokuta, Nigeria',
                'gsm' => 160,
                'width_inches' => 46,
                'care' => 'Machine wash cold with like colours. Iron medium.',
                'drape' => 'Light and lively. Best on a Danshiki where the pattern is the point.',
                'variants' => [
                    ['Terracotta Bloom', '#E2620E', 'Wax print', 9_500_00, 220],
                    ['Indigo Geometric', '#2B3A67', 'Wax print', 9_500_00, 180],
                ],
            ],
            [
                'material' => 'cotton',
                'name' => 'Shadda Cotton',
                'origin' => 'Kano, Nigeria',
                'gsm' => 200,
                'width_inches' => 54,
                'care' => 'Hand wash. Beat to restore the sheen, as it is traditionally finished.',
                'drape' => 'Dense and glossy. Holds embroidery beautifully.',
                'variants' => [
                    ['Gold', '#C9A227', null, 13_000_00, 110],
                    ['Deep Green', '#1F4F3F', null, 12_500_00, 95],
                ],
            ],
            [
                'material' => 'nylon',
                'name' => 'Travel Nylon Blend',
                'origin' => 'Imported',
                'gsm' => 140,
                'width_inches' => 58,
                'care' => 'Machine wash cold, hang dry. Does not need ironing.',
                'drape' => 'Light and springy. For customers who live on aeroplanes.',
                'variants' => [
                    ['Charcoal', '#3A3A3A', null, 8_000_00, 240],
                    ['Stone', '#9A9287', null, 8_000_00, 200],
                ],
            ],
        ];

        foreach ($fabrics as $i => $data) {
            $fabric = Fabric::updateOrCreate(['slug' => Str::slug($data['name'])], [
                'fabric_material_id' => $materialModels[$data['material']]->id,
                'name' => $data['name'],
                'description' => $data['drape'],
                'care_instructions' => $data['care'],
                'drape_notes' => $data['drape'],
                'origin' => $data['origin'],
                'gsm' => $data['gsm'],
                'width_inches' => $data['width_inches'],
                'is_active' => true,
                'sort_order' => $i,
            ]);

            foreach ($data['variants'] as [$colour, $hex, $pattern, $priceKobo, $stock]) {
                FabricVariant::updateOrCreate(
                    ['sku' => Str::upper(Str::slug($data['name'].'-'.$colour))],
                    [
                        'fabric_id' => $fabric->id,
                        'colour_name' => $colour,
                        'colour_hex' => $hex,
                        'pattern' => $pattern,
                        'price_per_yard_kobo' => $priceKobo,
                        'stock_yards' => $stock,
                        'reserved_yards' => 0,
                        'low_stock_threshold_yards' => 15,
                        'min_order_yards' => 1.00,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
