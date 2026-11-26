<?php

use App\Livewire\Trips\Show as TripShow;
use App\Models\HostProfile;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\Visibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * "More from this host": the row under a trip's reviews that shows the same host's other trips. It is the host's own
 * ground, so it only ever carries that host's trips, only those this viewer could find in a listing, one tile per
 * offering, and never the offering already on the page.
 *
 * Both Livewire::actingAs() and $this->actingAs() stay signed in for the rest of the test, so each test looks at the
 * guest first, then the follower, then the host.
 */

/** A host with a page of their own, unless `$handle` is null. */
function moreFromHostUser(string $name = 'Ridgeline Treks', ?string $handle = 'ridgeline'): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => $handle, 'name' => $name]);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

/** A published trip of `$host` with one open departure `$days` days from now (a negative number is one that has gone). */
function moreFromHostTrip(User $host, string $title, int $days = 10, array $attrs = []): Trip
{
    $trip = Trip::factory()->published()->create($attrs + ['host_id' => $host->id, 'title' => $title]);
    TripDeparture::factory()->for($trip)->create(['starts_at' => now()->addDays($days)]);

    return $trip;
}

/**
 * The viewed trip `a` and its other schedule, the one trip that qualifies (`b`), and four that must stay out: one whose
 * only departure has gone, one that is link only, another host's, and the sibling schedule of the viewed trip.
 */
function moreFromHostSetup(): array
{
    $host = moreFromHostUser();
    $group = (string) Str::uuid();

    return [
        'host' => $host,
        'a' => moreFromHostTrip($host, 'Pulag Sunrise Climb', 7, ['trip_group_id' => $group]),
        'sibling' => moreFromHostTrip($host, 'Pulag Sunrise Climb', 21, ['trip_group_id' => $group]),
        'b' => moreFromHostTrip($host, 'Sagada Cave Connection', 14),
        'pastOnly' => moreFromHostTrip($host, 'Batad Terraces Walk', -10),
        'link' => moreFromHostTrip($host, 'Private Barkada Hike', 12, ['visibility' => Visibility::LINK]),
        'otherHostTrip' => moreFromHostTrip(moreFromHostUser('Summit Collective', 'summitco'), 'Mayon Crater Rim', 9),
    ];
}

/** The heading element that says "More from ...", as the page prints it; empty when the page has none. */
function moreFromHostHeading(string $html): string
{
    return preg_match('#<h2[^>]*>(?:(?!</h2>).)*More from(?:(?!</h2>).)*</h2>#s', $html, $found) ? $found[0] : '';
}

it('shows the host\'s other trips and never another host\'s', function () {
    ['host' => $host, 'a' => $a, 'b' => $b, 'otherHostTrip' => $otherHostTrip] = moreFromHostSetup();

    $this->get(route('trips.show', $a))->assertOk()
        ->assertSee('More from '.$host->name)
        ->assertSee($b->title)->assertSee(route('trips.show', $b))
        ->assertDontSee($otherHostTrip->title)->assertDontSee(route('trips.show', $otherHostTrip));
});

it('leaves out the offering being viewed, trips with nothing open, and unlisted trips for guests', function () {
    ['host' => $host, 'a' => $a, 'b' => $b] = moreFromHostSetup();

    // a departure that is cancelled or full is as good as none, and a draft is no one's listing
    moreFromHostTrip($host, 'Called Off Traverse', 15)->departures()->update(['status' => 'cancelled']);
    moreFromHostTrip($host, 'Sold Out Loop', 16)->departures()->update(['status' => 'full']);
    moreFromHostTrip($host, 'Unfinished Draft', 17, ['status' => 'draft']);

    Livewire::test(TripShow::class, ['trip' => $a])
        ->assertViewHas('more', fn ($more) => $more->pluck('id')->all() === [$b->id]);   // only $b qualifies
});

it('shows a follower the host\'s followers-first trips without telling them how a trip is listed', function () {
    ['host' => $host, 'a' => $a, 'link' => $link] = moreFromHostSetup();
    $early = moreFromHostTrip($host, 'Early Bird Traverse', 11, ['visibility' => Visibility::FOLLOWERS, 'public_at' => now()->addDays(3)]);
    $follower = User::factory()->create();
    $follower->follow($host);

    $guest = Livewire::test(TripShow::class, ['trip' => $a]);
    expect($guest->viewData('more')->pluck('id'))->not->toContain($early->id)->not->toContain($link->id);
    $guest->assertDontSee('Early Bird Traverse');

    $member = Livewire::actingAs($follower)->test(TripShow::class, ['trip' => $a]);
    expect($member->viewData('more')->pluck('id'))->toContain($early->id)->not->toContain($link->id);
    $member->assertSee('Early Bird Traverse')->assertDontSee('Followers first')->assertDontSee('Link only');
});

it('shows the host their own unlisted trips, each carrying its chip, and another host only what is listed', function () {
    ['host' => $host, 'a' => $a, 'b' => $b, 'link' => $link] = moreFromHostSetup();
    $early = moreFromHostTrip($host, 'Early Bird Traverse', 11, ['visibility' => Visibility::FOLLOWERS, 'public_at' => now()->addDays(3)]);

    // another host looking: the first thing checked, since a test that has acted as someone stays them
    Livewire::actingAs(moreFromHostUser('Rival Guides', 'rivals'))->test(TripShow::class, ['trip' => $a])
        ->assertViewHas('more', fn ($more) => $more->pluck('id')->all() === [$b->id])
        ->assertDontSee('Link only');

    Livewire::actingAs($host)->test(TripShow::class, ['trip' => $a])
        ->assertViewHas('more', fn ($more) => $more->contains('id', $link->id) && $more->contains('id', $early->id) && $more->contains('id', $b->id))
        ->assertSee('Link only')->assertSee('Followers first until');
});

it('lists the host\'s followers-first trips as soon as the viewer follows them from this page', function () {
    ['host' => $host, 'a' => $a] = moreFromHostSetup();
    $early = moreFromHostTrip($host, 'Early Bird Traverse', 11, ['visibility' => Visibility::FOLLOWERS, 'public_at' => now()->addDays(3)]);

    // the Follow button sits in the host card right above this row, and the row is worked out afresh on every update
    Livewire::actingAs(User::factory()->create(['role' => 'joiner']))->test(TripShow::class, ['trip' => $a])
        ->assertViewHas('more', fn ($more) => $more->doesntContain('id', $early->id))
        ->call('toggleFollow', $host->id)
        ->assertViewHas('more', fn ($more) => $more->contains('id', $early->id));
});

it('sits after the reviews and before the booking card', function () {
    ['a' => $a] = moreFromHostSetup();

    $html = $this->get(route('trips.show', $a))->getContent();

    expect(strpos($html, 'More from'))->toBeGreaterThan(strpos($html, '>Reviews'))->toBeLessThan(strpos($html, 'Join this trip'));
});

it('shows one tile per offering, the soonest schedule standing for it', function () {
    ['host' => $host, 'a' => $a, 'b' => $b] = moreFromHostSetup();
    $group = (string) Str::uuid();
    $later = moreFromHostTrip($host, 'Anawangin Cove', 40, ['trip_group_id' => $group]);
    $sooner = moreFromHostTrip($host, 'Anawangin Cove', 20, ['trip_group_id' => $group]);

    Livewire::test(TripShow::class, ['trip' => $a])
        ->assertViewHas('more', fn ($more) => $more->pluck('id')->all() === [$b->id, $sooner->id]   // soonest first, one for the pair
            && $more->last()->schedule_count === 2)
        ->assertDontSee('href="'.route('trips.show', $later).'"', false);   // exactly: the pair's second slug starts with the first's
});

it('shows at most six tiles, the six whose next departure is soonest', function () {
    $host = moreFromHostUser();
    $a = moreFromHostTrip($host, 'Pulag Sunrise Climb', 7);

    // eight other offerings, the third of them with a second, later schedule
    $offerings = collect(range(1, 8))->map(fn ($n) => moreFromHostTrip($host, "Offering {$n}", 10 + $n));
    $group = (string) Str::uuid();
    $offerings[2]->update(['trip_group_id' => $group]);
    moreFromHostTrip($host, 'Offering 3', 50, ['trip_group_id' => $group]);

    Livewire::test(TripShow::class, ['trip' => $a])
        ->assertViewHas('more', fn ($more) => $more->pluck('id')->all() === $offerings->take(6)->pluck('id')->all());
});

it('dates each tile by its next open departure, not by one that has gone', function () {
    // `M j` has no zero padding, so on some real days the date that must be absent is the front of one the page prints
    // ("Dec 2" in "Dec 26"). On this day the shown date is "Dec 26" and the absent one "Nov 12", whenever the test runs.
    $this->travelTo('2026-11-26 09:00:00');

    $host = moreFromHostUser();
    $a = moreFromHostTrip($host, 'Pulag Sunrise Climb', 7);

    // a finished run is the oldest row, so only a constrained load keeps it out of the ordering and out of the tile
    $running = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Wide Open Traverse']);
    TripDeparture::factory()->completed()->for($running)->create();
    TripDeparture::factory()->for($running)->create(['starts_at' => now()->addDays(30)]);
    $soon = moreFromHostTrip($host, 'Soon Enough Hike', 10);

    Livewire::test(TripShow::class, ['trip' => $a])
        ->assertViewHas('more', fn ($more) => $more->pluck('id')->all() === [$soon->id, $running->id])
        ->assertSee(now()->addDays(30)->format('M j'))
        ->assertDontSee(now()->subWeeks(2)->format('M j'));
});

it('is absent when the host has nothing else to show', function () {
    $onlyTrip = moreFromHostTrip(moreFromHostUser('Lone Trail Guides', 'lonetrail'), 'Pulag Sunrise Climb');
    $this->get(route('trips.show', $onlyTrip))->assertOk()->assertDontSee('More from');

    // and when everything else they run is the viewed offering, a trip that has gone, or unlisted
    ['a' => $a, 'b' => $b] = moreFromHostSetup();
    $b->delete();
    Livewire::test(TripShow::class, ['trip' => $a])->assertViewHas('more', fn ($more) => $more->isEmpty())->assertDontSee('More from');
});

it('links its heading to the host\'s page, or is a plain heading for a host without one', function () {
    ['host' => $host, 'a' => $a] = moreFromHostSetup();

    $heading = moreFromHostHeading($this->get(route('trips.show', $a))->getContent());
    expect($heading)->toContain('href="'.route('hosts.show', $host).'"')->toContain('More from Ridgeline Treks');

    $drifter = moreFromHostUser('Drifter Treks', null);
    $first = moreFromHostTrip($drifter, 'Mount Ulap Loop');
    moreFromHostTrip($drifter, 'Mount Pulag Ridge');

    $plain = moreFromHostHeading($this->get(route('trips.show', $first))->getContent());
    expect($plain)->toContain('More from Drifter Treks')->not->toContain('<a ');
});

it('keeps a host\'s name from becoming markup in its heading', function () {
    $host = moreFromHostUser('Trail <i>Co</i> & "Sons"');
    $a = moreFromHostTrip($host, 'Pulag Sunrise Climb', 7);
    moreFromHostTrip($host, 'Sagada Cave Connection', 14);

    $heading = moreFromHostHeading($this->get(route('trips.show', $a))->getContent());
    expect($heading)->toContain('More from Trail &lt;i&gt;Co&lt;/i&gt; &amp; &quot;Sons&quot;')->not->toContain('<i>Co</i>');
});

it('loads its tiles without a query for each one', function () {
    $host = moreFromHostUser();
    $a = moreFromHostTrip($host, 'Pulag Sunrise Climb', 7);
    $queries = function () use ($a) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::test(TripShow::class, ['trip' => Trip::find($a->id)])->assertSee('More from');

        return count(DB::getQueryLog());
    };

    moreFromHostTrip($host, 'Offering 1', 11);
    $one = $queries();
    foreach (range(2, 6) as $n) {
        moreFromHostTrip($host, "Offering {$n}", 10 + $n);
    }
    $six = $queries();

    // five more tiles may each count their own spots left, as the tile does wherever it is drawn, and nothing else
    expect($six - $one)->toBeLessThanOrEqual(5);
});
