<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reviewable_type' => Trip::class,
            'reviewable_id' => Trip::factory(),
            'joiner_id' => User::factory()->state(['role' => 'joiner']),
            'rating' => fake()->numberBetween(4, 5),
            'body' => fake()->sentence(),
        ];
    }
}
