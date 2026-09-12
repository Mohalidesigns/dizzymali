<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<\App\Models\GarmentType> */
class GarmentTypeFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Agbada', 'Kaftan', 'Jalabiya', 'Danshiki']).' '.fake()->word();

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'tagline' => fake()->sentence(),
            'base_sewing_cost_kobo' => 30_000_00,
            'default_yardage' => 4.00,
            'base_weight_grams' => 700,
            'grams_per_yard' => 220,
            'requires_top_measurements' => true,
            'requires_trouser_measurements' => false,
            'lead_time_days' => 21,
            'is_active' => true,
        ];
    }
}
