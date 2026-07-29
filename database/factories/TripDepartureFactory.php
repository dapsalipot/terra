<?php

namespace Database\Factories;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripDepartureFactory extends Factory
{
    public function completed(): static
    {
        return $this->state(['status' => 'completed', 'starts_at' => now()->subWeeks(2), 'ends_at' => now()->subWeeks(2)->addDays(2)]);
    }

    public function definition(): array
    {
        $start = now()->addWeeks(fake()->numberBetween(1, 8));

        return [
            'trip_id' => Trip::factory(),
            'starts_at' => $start,
            'ends_at' => (clone $start)->addDays(2),
            'capacity' => fake()->numberBetween(8, 16),
            'status' => 'open',
        ];
    }
}
