<?php

namespace Database\Factories;

use App\Models\Place;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripFactory extends Factory
{
    public function definition(): array
    {
        return [
            'host_id' => User::factory()->state(['role' => 'host']),
            'place_id' => Place::factory(),
            'title' => fake()->unique()->words(3, true),
            'description' => fake()->paragraph(),
            'meetup_point' => fake()->city().' gate',
            'price' => fake()->numberBetween(60, 220),
            'inclusions' => ['Transport', 'Permits', 'Guide', 'Meals'],
            'status' => 'draft',
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => 'published']);
    }
}
