<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Address> */
class AddressFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Home',
            'recipient_name' => fake()->name(),
            'phone' => '+234'.fake()->numerify('##########'),
            'line_1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postcode' => fake()->postcode(),
            'country_code' => 'NG',
            'is_default' => true,
        ];
    }
}
