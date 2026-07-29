<?php

namespace Database\Factories;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'method' => 'Bank transfer',
            'reference_no' => (string) fake()->numberBetween(100000000, 999999999),
            'submitted_at' => now(),
        ];
    }
}
