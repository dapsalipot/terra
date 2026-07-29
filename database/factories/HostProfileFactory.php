<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class HostProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bio' => fake()->sentence(12),
            'currency' => 'PHP',
            'payment_methods' => [
                ['label' => 'Bank transfer', 'detail' => '•••• '.fake()->numberBetween(1000, 9999)],
            ],
            'trips_count' => fake()->numberBetween(0, 50),
            'guests_count' => fake()->numberBetween(0, 500),
            'followers_count' => fake()->numberBetween(0, 3000),
            'rating' => fake()->randomFloat(1, 4.4, 5.0),
        ];
    }
}
