<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'host_id' => User::factory()->state(['role' => 'host']),
            'type' => 'update',
            'body' => fake()->sentence(12),
        ];
    }
}
