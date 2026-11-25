<?php

namespace Database\Factories;

use App\Models\Place;
use App\Models\User;
use App\Support\Visibility;
use DateTimeInterface;
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

    /** Unlisted everywhere: reachable by its link and nothing else. */
    public function linkOnly(): static
    {
        return $this->state(['visibility' => Visibility::LINK]);
    }

    /** Listed to the host's followers first, then to everyone from `$opensAt` (three days out unless given). */
    public function followersFirst(?DateTimeInterface $opensAt = null): static
    {
        return $this->state([
            'visibility' => Visibility::FOLLOWERS,
            'public_at' => $opensAt ?? now()->addDays(3),
        ]);
    }
}
