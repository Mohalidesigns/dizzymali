<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<\App\Models\FabricMaterial> */
class FabricMaterialFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return ['slug' => Str::slug($name), 'name' => ucfirst($name), 'is_active' => true];
    }
}
