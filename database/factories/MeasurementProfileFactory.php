<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\MeasurementProfile> */
class MeasurementProfileFactory extends Factory
{
    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'My measurements',
            'unit_preference' => 'in',
            'source' => 'manual',
            'review_status' => 'ready',
            'is_default' => false,
        ];
    }

    public function pendingReview(): static
    {
        return $this->state(fn () => ['review_status' => 'pending_review', 'source' => 'uploaded']);
    }
}
