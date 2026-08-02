<?php

use App\Livewire\Joiner\Dashboard;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** A joiner with real bookings, each carrying travellers. */
function joinerWithBookings(int $count): User
{
    $user = User::factory()->create(['role' => 'joiner']);

    foreach (range(1, $count) as $i) {
        $trip = Trip::factory()->create(['status' => 'published']);
        $departure = TripDeparture::factory()->create([
            'trip_id' => $trip->id,
            'capacity' => 10,
            'starts_at' => now()->addWeeks($i),
        ]);
        $reservation = Reservation::factory()->create([
            'trip_departure_id' => $departure->id,
            'joiner_id' => $user->id,
            'status' => 'confirmed',
            'party_size' => 3,
        ]);
        foreach (range(0, 2) as $position) {
            $reservation->participants()->create([
                'position' => $position,
                'first_name' => 'Ada',
                'last_name' => 'Reyes',
                'birthday' => '1995-01-01',
            ]);
        }
    }

    return $user;
}

/**
 * Count the queries one render issues.
 *
 * The closure MUST use `use (&$queries)`. An arrow function binds by value, so `$queries[]`
 * would append to a copy and the count would come back zero — which looks like a passing
 * result rather than a broken probe.
 */
function queriesForRender(User $user): array
{
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    Livewire::actingAs($user)->test(Dashboard::class);

    return $queries;
}

it('renders within its query budget', function () {
    $queries = queriesForRender(joinerWithBookings(6));

    // Measured before this work: 18, of which 6 were a per-booking price-tier lookup.
    // The ceiling is 10 rather than 8 so an unrelated session or framework query does not
    // break the test, while still failing loudly if the eager load is dropped.
    expect(count($queries))->toBeLessThanOrEqual(10);
});

it('does not issue more queries as bookings grow', function () {
    // A ceiling alone can be satisfied by a fixed-size N+1. Only the comparison proves flatness.
    $few = count(queriesForRender(joinerWithBookings(3)));
    $many = count(queriesForRender(joinerWithBookings(12)));

    expect($many)->toBe($few);
});

it('never looks up price tiers one booking at a time', function () {
    $queries = queriesForRender(joinerWithBookings(6));

    $tierQueries = collect($queries)->filter(fn ($sql) => str_contains($sql, 'trip_price_tiers'));

    // One eager-loaded lookup is correct. Six is the N+1.
    expect($tierQueries->count())->toBeLessThanOrEqual(1);
});

it('does not build recommendations for a joiner who has bookings', function () {
    // The recommendations query is a four-join correlated subquery rendered only in the empty
    // state, so computing it for someone with bookings is pure waste.
    $queries = queriesForRender(joinerWithBookings(3));

    $recommendationQueries = collect($queries)
        ->filter(fn ($sql) => str_contains($sql, 'trip_destinations'));

    expect($recommendationQueries)->toBeEmpty();
});

it('still recommends trips to a joiner with nothing booked', function () {
    $user = User::factory()->create(['role' => 'joiner']);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertViewHas('recommended');
});
