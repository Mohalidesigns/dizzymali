<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GarmentType;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\OrderItem> */
class OrderItemFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'garment_type_id' => GarmentType::factory(),
            'quantity' => 1,
        ];
    }
}
