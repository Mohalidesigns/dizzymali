<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MeasurementField;
use Illuminate\Database\Seeder;

/**
 * The thirteen measurable dimensions DizzyMali works to.
 *
 * The min/max bounds are deliberately generous — they exist to catch a typo
 * (a 12-inch chest) not to refuse an unusual body. Help text is written for
 * someone holding a tape measure alone in a bedroom, not for a tailor.
 */
class MeasurementFieldSeeder extends Seeder
{
    public function run(): void
    {
        $fields = [
            // group, key, label, unit type, min, max, help text
            ['top', 'chest', 'Chest', 'circumference', 24, 70, 'Around the fullest part of your chest, under the arms. Keep the tape level and breathe normally.'],
            ['top', 'shoulder', 'Shoulder', 'length', 12, 30, 'Across the back, from the bony point of one shoulder to the other.'],
            ['top', 'tommy', 'Tommy (stomach)', 'circumference', 24, 75, 'Around the widest part of your stomach. Stand relaxed — do not hold it in.'],
            ['top', 'shirt_length', 'Shirt length', 'length', 20, 65, 'From the top of the shoulder, beside the neck, straight down to where you want the garment to end.'],
            ['top', 'neck', 'Neck', 'circumference', 10, 26, 'Around the base of the neck, with one finger between the tape and your skin.'],
            ['top', 'sleeve', 'Sleeve', 'length', 15, 42, 'From the shoulder point, down the outside of a slightly bent arm, to the wrist bone.'],
            ['top', 'round_sleeve', 'Round sleeve', 'circumference', 8, 30, 'Around the fullest part of the upper arm, with the arm relaxed at your side.'],
            ['trouser', 'waist', 'Waist', 'circumference', 22, 70, 'Around where you actually wear your trousers, not your natural waist.'],
            ['trouser', 'hip', 'Hip', 'circumference', 26, 75, 'Around the fullest part of the seat, with your feet together.'],
            ['trouser', 'thigh_lap', 'Thigh / lap', 'circumference', 14, 40, 'Around the fullest part of one thigh, close to the crotch.'],
            ['trouser', 'length', 'Trouser length', 'length', 24, 55, 'From the waistband, down the outside of the leg, to where you want the hem.'],
            ['trouser', 'knee', 'Knee', 'circumference', 10, 32, 'Around the knee, standing straight.'],
            ['trouser', 'foot', 'Foot opening', 'circumference', 8, 28, 'Around the trouser opening at the ankle — how wide you want the leg to finish.'],
        ];

        foreach ($fields as $i => [$group, $key, $label, $unitType, $min, $max, $help]) {
            MeasurementField::updateOrCreate(['key' => $key], [
                'group' => $group,
                'label' => $label,
                'help_text' => $help,
                'unit_type' => $unitType,
                'min_inches' => $min,
                'max_inches' => $max,
                'sort_order' => $i,
                'is_active' => true,
            ]);
        }
    }
}
