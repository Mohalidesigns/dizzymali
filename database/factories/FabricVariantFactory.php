<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Fabric;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<\App\Models\FabricVariant> */
class FabricVariantFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        $colour = fake()->unique()->colorName();

        return [
            'fabric_id' => Fabric::factory(),
            'sku' => Str::upper(Str::slug($colour.'-'.fake()->unique()->numerify('###'))),
            'colour_name' => ucfirst($colour),
            'colour_hex' => fake()->hexColor(),
            'price_per_yard_kobo' => 15_000_00,
            'stock_yards' => 200,
            'reserved_yards' => 0,
            'low_stock_threshold_yards' => 15,
            'min_order_yards' => 1,
            'is_active' => true,
        ];
    }
}
