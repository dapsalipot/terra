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

    /*
     * 18 before this work began, of which 6 were a per-booking price-tier lookup.
     *
     * The 13 now issued are each one grouped query for a distinct thing the page shows:
     * reservations, departures, trips, hosts, price tiers, places, participants, reviews,
     * orphaned guest bookings, holidays, per-day trip availability, and — the two most recent —
     * the trips and places behind the cover photos stacked on each joinable day.
     *
     * Those last two bought the calendar its photos in place of a bare number nobody could
     * read the meaning of. They are two for the whole grid, not two per trip, which is the
     * distinction that matters: the sibling test below pins that flatness and would fail if
     * this ever became per-row.
     *
     * One more since: the trail figures behind the "where you have been" totals, eager-loaded
     * through trip_destinations. Also one for the page rather than one per past trip.
     *
     * The ceiling sits one above. It is not a target to grow into: an N+1 regression costs six
     * or more at this fixture size, so it still fails loudly.
     */
    expect(count($queries))->toBeLessThanOrEqual(15);
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

    /*
     * `trip_destinations` alone stopped identifying this query: the trail-stats eager load joins
     * the same table, so the old needle matched a query that is supposed to run and the test
     * failed on a change that was correct. What is unique to the recommendation is the
     * correlated shape — it reaches trip_destinations *and* reservations in one statement, which
     * nothing else on this page does.
     */
    $recommendationQueries = collect($queries)
        ->filter(fn ($sql) => str_contains($sql, 'trip_destinations') && str_contains($sql, 'reservations'));

    expect($recommendationQueries)->toBeEmpty();
});

it('still recommends trips to a joiner with nothing booked', function () {
    $user = User::factory()->create(['role' => 'joiner']);

    Livewire::actingAs($user)->test(Dashboard::class)
        ->assertViewHas('recommended');

    /*
     * The other half of the pair, and the reason it matters: the sibling test asserts the
     * recommendation query is ABSENT, which a needle matching nothing at all would also satisfy.
     * Running the same filter against a render that genuinely builds recommendations proves the
     * needle can fire, so the absence assertion means something.
     */
    $queries = queriesForRender($user);

    $recommendationQueries = collect($queries)
        ->filter(fn ($sql) => str_contains($sql, 'trip_destinations') && str_contains($sql, 'reservations'));

    expect($recommendationQueries)->not->toBeEmpty();
});
