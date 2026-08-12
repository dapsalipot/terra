<?php

namespace Database\Factories;

use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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
            /*
             * Derived from whatever starts_at ends up being, not from the default above.
             *
             * As a plain value it was computed from this random $start and then left alone when a
             * test passed its own starts_at — so `['starts_at' => now()->addWeeks(2)]` produced a
             * departure running two weeks out and ending six weeks later, or ending before it
             * began. Fixtures meant to be three-day trips were silently months long, which made
             * every calendar cell in a test overlap every other.
             */
            'ends_at' => fn (array $attributes) => Carbon::parse($attributes['starts_at'])->copy()->addDays(2),
            'capacity' => fake()->numberBetween(8, 16),
            'status' => 'open',
        ];
    }
}
