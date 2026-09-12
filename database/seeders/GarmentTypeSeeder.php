<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GarmentType;
use App\Models\MeasurementField;
use App\Models\YardageRule;
use Illuminate\Database\Seeder;

/**
 * The four garments, with placeholder commercial figures.
 *
 * Every number below is a placeholder pending Mohammed's real costs. They are
 * internally consistent — an Agbada takes more cloth and more labour than a
 * Danshiki — so the engine can be tested end to end, but they are not prices
 * anyone should sell at.
 */
class GarmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $garments = [
            [
                'slug' => 'agbada',
                'name' => 'Agbada',
                'tagline' => 'The full ceremonial three-piece. Weddings, naming ceremonies, Eid.',
                'description' => 'A flowing wide-sleeved robe worn over a long tunic and trousers. The most cloth, the most hand-finishing, and the garment people photograph.',
                'base_sewing_cost_kobo' => 45_000_00,
                'default_yardage' => 5.00,
                'base_weight_grams' => 900,
                'grams_per_yard' => 240,
                'requires_top_measurements' => true,
                'requires_trouser_measurements' => true,
                'lead_time_days' => 28,
                'sort_order' => 1,
                'rules' => [
                    ['chest', 44, 0.25],
                    ['chest', 48, 0.50],
                    ['chest', 52, 0.75],
                    ['shirt_length', 58, 0.50],
                ],
            ],
            [
                'slug' => 'kaftan',
                'name' => 'Kaftan',
                'tagline' => 'Two-piece. Friday mosque, dinners, everyday formal.',
                'description' => 'A straight-cut long tunic with matching trousers. Quietly formal and the most versatile thing in the wardrobe.',
                'base_sewing_cost_kobo' => 28_000_00,
                'default_yardage' => 4.00,
                'base_weight_grams' => 700,
                'grams_per_yard' => 220,
                'requires_top_measurements' => true,
                'requires_trouser_measurements' => true,
                'lead_time_days' => 21,
                'sort_order' => 2,
                'rules' => [
                    ['chest', 46, 0.50],
                    ['chest', 52, 0.75],
                ],
            ],
            [
                'slug' => 'jalabiya',
                'name' => 'Jalabiya',
                'tagline' => 'One piece, ankle length. Comfort without losing the occasion.',
                'description' => 'A single flowing ankle-length robe. No trousers, no fuss — cut for heat and for movement.',
                'base_sewing_cost_kobo' => 22_000_00,
                'default_yardage' => 3.50,
                'base_weight_grams' => 600,
                'grams_per_yard' => 210,
                'requires_top_measurements' => true,
                'requires_trouser_measurements' => false,
                'lead_time_days' => 18,
                'sort_order' => 3,
                'rules' => [
                    ['chest', 46, 0.50],
                    ['shirt_length', 58, 0.50],
                ],
            ],
            [
                'slug' => 'danshiki',
                'name' => 'Danshiki',
                'tagline' => 'Short, embroidered at the neck. Smart casual, any day of the week.',
                'description' => 'A loose pullover top finishing around the hip, traditionally embroidered at the neckline and cuffs.',
                'base_sewing_cost_kobo' => 15_000_00,
                'default_yardage' => 2.50,
                'base_weight_grams' => 400,
                'grams_per_yard' => 200,
                'requires_top_measurements' => true,
                'requires_trouser_measurements' => false,
                'lead_time_days' => 14,
                'sort_order' => 4,
                'rules' => [
                    ['chest', 48, 0.25],
                ],
            ],
        ];

        $fields = MeasurementField::all()->keyBy('key');

        foreach ($garments as $data) {
            $rules = $data['rules'];
            unset($data['rules']);

            $garment = GarmentType::updateOrCreate(['slug' => $data['slug']], $data + ['is_active' => true]);

            $attach = [];
            $order = 0;

            foreach ($fields as $key => $field) {
                $isTop = $field->group->value === 'top';

                if ($isTop && ! $garment->requires_top_measurements) {
                    continue;
                }

                if (! $isTop && ! $garment->requires_trouser_measurements) {
                    continue;
                }

                $attach[$field->id] = ['is_required' => true, 'sort_order' => $order++];
            }

            $garment->measurementFields()->sync($attach);

            $garment->yardageRules()->delete();

            foreach ($rules as $i => [$fieldKey, $threshold, $additional]) {
                $field = $fields->get($fieldKey);

                if ($field === null) {
                    continue;
                }

                YardageRule::create([
                    'garment_type_id' => $garment->id,
                    'measurement_field_id' => $field->id,
                    'threshold_inches' => $threshold,
                    'additional_yards' => $additional,
                    'is_active' => true,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
