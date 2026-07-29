<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Mountain',
            'country' => fake()->country(),
            'region' => fake()->state(),
            'elevation_m' => fake()->numberBetween(800, 4000),
            'difficulty' => 'challenging',
            'category' => 'hike',
        ];
    }
}
