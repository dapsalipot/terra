<?php

namespace Database\Factories;

use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_departure_id' => TripDeparture::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
            'pinned' => false,
        ];
    }
}
