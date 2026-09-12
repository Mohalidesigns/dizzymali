<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\GarmentOption;
use App\Models\GarmentOptionGroup;
use App\Models\GarmentType;
use Illuminate\Database\Seeder;

class GarmentOptionSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'slug' => 'embroidery',
                'name' => 'Embroidery',
                'help_text' => 'Hand and machine work at the neckline, cuffs and chest. The single biggest change you can make to how ceremonial a garment looks.',
                'is_required' => false,
                'allows_multiple' => false,
                'garments' => ['agbada', 'kaftan', 'jalabiya', 'danshiki'],
                'options' => [
                    ['none', 'None', 0, 0, 0, true, 'Clean, unembroidered. Lets the fabric speak.'],
                    ['light', 'Light — neckline only', 8_000_00, 0, 2, false, 'A narrow band at the neckline.'],
                    ['standard', 'Standard — neck, chest and cuffs', 18_000_00, 0, 4, false, 'The usual choice for a wedding guest.'],
                    ['heavy', 'Heavy — full ceremonial', 45_000_00, 0.25, 10, false, 'Dense hand work across the chest and sleeves. Adds ten days.'],
                ],
            ],
            [
                'slug' => 'collar',
                'name' => 'Collar',
                'help_text' => null,
                'is_required' => false,
                'allows_multiple' => false,
                'garments' => ['kaftan', 'jalabiya', 'danshiki'],
                'options' => [
                    ['mandarin', 'Mandarin', 0, 0, 0, true, 'A short stand collar. The default.'],
                    ['round', 'Round neck', 0, 0, 0, false, 'No collar at all.'],
                    ['shirt', 'Shirt collar', 3_500_00, 0, 1, false, 'A conventional turned collar.'],
                ],
            ],
            [
                'slug' => 'lining',
                'name' => 'Lining',
                'help_text' => 'A lining hangs better and hides the seams, at the cost of warmth.',
                'is_required' => false,
                'allows_multiple' => false,
                'garments' => ['agbada', 'kaftan'],
                'options' => [
                    ['unlined', 'Unlined', 0, 0, 0, true, 'Cooler. The right choice for Lagos.'],
                    ['half', 'Half lined', 9_000_00, 0.50, 2, false, 'Lined through the shoulders and chest only.'],
                    ['full', 'Fully lined', 16_000_00, 1.00, 3, false, 'Best for cooler climates and heavier cloth.'],
                ],
            ],
            [
                'slug' => 'pockets',
                'name' => 'Pockets',
                'help_text' => null,
                'is_required' => false,
                'allows_multiple' => false,
                'garments' => ['agbada', 'kaftan', 'jalabiya'],
                'options' => [
                    ['side', 'Side pockets', 0, 0, 0, true, 'Two, in the side seams.'],
                    ['side_chest', 'Side and chest', 2_500_00, 0, 0, false, 'Adds a patch pocket at the chest.'],
                    ['inner', 'Add an inner pocket', 4_000_00, 0, 1, false, 'A concealed inner pocket for a phone or passport.'],
                ],
            ],
        ];

        $garmentTypes = GarmentType::all()->keyBy('slug');

        foreach ($groups as $i => $data) {
            $group = GarmentOptionGroup::updateOrCreate(['slug' => $data['slug']], [
                'name' => $data['name'],
                'help_text' => $data['help_text'],
                'is_required' => $data['is_required'],
                'allows_multiple' => $data['allows_multiple'],
                'is_active' => true,
                'sort_order' => $i,
            ]);

            foreach ($data['options'] as $j => [$slug, $name, $surcharge, $yards, $leadDays, $isDefault, $description]) {
                GarmentOption::updateOrCreate(
                    ['garment_option_group_id' => $group->id, 'slug' => $slug],
                    [
                        'name' => $name,
                        'description' => $description,
                        'surcharge_kobo' => $surcharge,
                        'additional_yards' => $yards,
                        'additional_lead_days' => $leadDays,
                        'is_default' => $isDefault,
                        'is_active' => true,
                        'sort_order' => $j,
                    ],
                );
            }

            foreach ($data['garments'] as $slug) {
                $garment = $garmentTypes->get($slug);

                if ($garment !== null) {
                    $garment->optionGroups()->syncWithoutDetaching([$group->id => ['sort_order' => $i]]);
                }
            }
        }
    }
}
