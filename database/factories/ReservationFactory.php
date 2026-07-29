<?php

namespace Database\Factories;

use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_departure_id' => TripDeparture::factory(),
            'joiner_id' => User::factory()->state(['role' => 'joiner']),
            'source' => 'app',
            'party_size' => 1,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed', 'confirmed_at' => now(), 'expires_at' => null]);
    }

    public function manual(): static
    {
        return $this->state([
            'source' => 'manual',
            'joiner_id' => null,
            'guest_name' => fake()->name(),
            'status' => 'confirmed',
            'expires_at' => null,
        ]);
    }
}
