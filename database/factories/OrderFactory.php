<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'reference' => Order::generateReference(),
            'user_id' => User::factory(),
            'status' => OrderStatus::Draft,
            'currency_code' => 'NGN',
            'service_level' => 'standard',
            'wizard_step' => 1,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => OrderStatus::Paid,
            'submitted_at' => now()->subDays(3),
            'placed_at' => now()->subDays(2),
            'promised_at' => now()->addDays(21),
        ]);
    }
}
