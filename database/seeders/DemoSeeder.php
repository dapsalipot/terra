<?php

namespace Database\Seeders;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Known demo logins (password: "password")
        $demoJoiner = User::factory()->create(['name' => 'Demo Joiner', 'email' => 'joiner@terra.test', 'role' => 'joiner']);
        $reviewers = User::factory()->count(4)->create(['role' => 'joiner']);

        $places = Place::all();

        collect(['Ridgeline' => 'ridgeline', 'Sunchaser' => 'sunchaser', 'Highland Co.' => 'highland'])
            ->each(function ($handle, $name) use ($places, $reviewers) {
                $host = User::factory()->create([
                    'name' => $name,
                    'email' => $handle.'@terra.test',
                    'role' => 'host',
                    'handle' => $handle,
                ]);
                HostProfile::factory()->for($host)->create([
                    'payment_methods' => [
                        ['label' => 'Bank transfer', 'detail' => '•••• 4821'],
                        ['label' => 'Wise', 'detail' => '@'.$handle],
                    ],
                ]);

                $places->random(3)->each(function (Place $place) use ($host, $reviewers) {
                    $suffix = match ($place->category) {
                        'island' => 'Island Escape',
                        'surf' => 'Surf Trip',
                        'city' => 'City Discovery',
                        'beach' => 'Beach Getaway',
                        default => fake()->randomElement(['Sunrise Traverse', 'Summit Trek', 'Ridge Expedition']),
                    };
                    $trip = Trip::factory()->published()->create([
                        'host_id' => $host->id,
                        'place_id' => $place->id,
                        'title' => $place->name.' '.$suffix,
                    ]);
                    TripDeparture::factory()->count(fake()->numberBetween(2, 3))->for($trip)->create();

                    // one completed departure with reviews, so ratings + the community wall populate
                    $done = TripDeparture::factory()->completed()->for($trip)->create();
                    $lines = [
                        'Never travelled with strangers before. Left with a group chat that is still going.',
                        'Unreal views and a crew that felt like old friends by the end.',
                        'Smooth from meetup to finish — the host handled everything.',
                    ];
                    $reviewers->random(fake()->numberBetween(2, 3))->each(function (User $joiner) use ($done, $trip, $lines) {
                        Reservation::factory()->confirmed()->create([
                            'trip_departure_id' => $done->id, 'joiner_id' => $joiner->id,
                        ]);
                        app(ReviewService::class)->record($joiner, $trip, [
                            'trip' => fake()->numberBetween(4, 5),
                            'host' => fake()->numberBetween(4, 5),
                            'place' => fake()->numberBetween(4, 5),
                        ], fake()->randomElement($lines));
                    });
                });
            });
    }
}
