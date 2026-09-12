<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FabricMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<\App\Models\Fabric> */
class FabricFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'fabric_material_id' => FabricMaterial::factory(),
            'slug' => Str::slug($name),
            'name' => ucwords($name),
            'gsm' => 200,
            'width_inches' => 58,
            'is_active' => true,
        ];
    }
}
