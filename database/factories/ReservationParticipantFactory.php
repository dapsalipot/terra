<?php

namespace Database\Factories;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationParticipantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'position' => 0,
            'is_booker' => false,
            'first_name' => fake()->firstName(),
            'middle_name' => null,
            'last_name' => fake()->lastName(),
            'birthday' => fake()->dateTimeBetween('-45 years', '-19 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(['male', 'female']),
            // A normal traveller now has someone to call. Tests that need the gap ask for it
            // explicitly via withoutEmergencyContact(), so "incomplete" stays a deliberate state
            // rather than the accidental default of every fixture in the suite.
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => '09'.fake()->numerify('#########'),
        ];
    }

    /** A seat booked before emergency contacts were asked for. */
    public function withoutEmergencyContact(): static
    {
        return $this->state(['emergency_contact_name' => null, 'emergency_contact_phone' => null]);
    }

    /** A seat whose details the booker deferred with "I'll add this later". */
    public function blank(): static
    {
        return $this->state([
            'first_name' => null, 'middle_name' => null, 'last_name' => null,
            'birthday' => null, 'gender' => null,
        ]);
    }
}
