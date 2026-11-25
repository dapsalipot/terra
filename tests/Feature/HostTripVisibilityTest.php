<?php

use App\Livewire\Host\Trips\Index as HostTripsIndex;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * Who can find a trip, changed from My trips: one control on each offering that writes all of its schedules at
 * once, and a chip on each schedule that says so when the trip is not listed to everyone.
 */

/** `$schedules` published trips of `$host` that share a group, as My trips shows one offering, each with a date to run. */
function myTripsOffering(User $host, int $schedules = 2, array $attrs = []): Collection
{
    $group = Str::uuid()->toString();

    return Collection::times($schedules, function ($n) use ($host, $group, $attrs) {
        $trip = Trip::factory()->published()->create($attrs + [
            'host_id' => $host->id,
            'trip_group_id' => $group,
            'title' => 'Pulag Sunrise',
        ]);
        TripDeparture::factory()->for($trip)->create(['starts_at' => now()->addWeeks($n)]);

        return $trip;
    });
}

/** What the schedules of `$group` say about who can find them, as [visibilities, opening dates], each de-duplicated. */
function myTripsListing(string $group): array
{
    $trips = Trip::where('trip_group_id', $group)->get();

    return [
        $trips->pluck('visibility')->unique()->values()->all(),
        $trips->map(fn ($t) => $t->public_at?->toDateTimeString())->unique()->values()->all(),
    ];
}

it('sets one visibility on every schedule of an offering', function () {
    $host = newWizardHost();
    $offering = myTripsOffering($host, 3);
    $group = $offering->first()->trip_group_id;
    $neighbour = myTripsOffering($host);   // another offering of the same host, left alone

    Livewire::actingAs($host)->test(HostTripsIndex::class)->call('setVisibility', $group, 'followers:1w');

    [$visibility, $opens] = myTripsListing($group);

    // one shared opening date, a week out, rather than three that merely look alike
    expect($visibility)->toBe(['followers'])
        ->and($opens)->toHaveCount(1)
        ->and(Carbon::parse($opens[0])->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()))->toBeTrue()
        ->and(myTripsListing($neighbour->first()->trip_group_id))->toBe([['public'], [null]]);
});

it('opens a followers-first window the preset length from now', function (string $window, string $opensAt) {
    $this->travelTo('2026-11-26 09:00:00');
    $host = newWizardHost();
    $group = myTripsOffering($host)->first()->trip_group_id;

    Livewire::actingAs($host)->test(HostTripsIndex::class)->call('setVisibility', $group, "followers:{$window}");

    expect(myTripsListing($group))->toBe([['followers'], [$opensAt]]);
})->with([
    '1 day' => ['1d', '2026-11-27 09:00:00'],
    '3 days' => ['3d', '2026-11-29 09:00:00'],
    '1 week' => ['1w', '2026-12-03 09:00:00'],
]);

it('clears the opening date for everyone and for link only', function () {
    $host = newWizardHost();
    $group = myTripsOffering($host, 2, ['visibility' => 'followers', 'public_at' => now()->addDays(3)])->first()->trip_group_id;
    $page = Livewire::actingAs($host)->test(HostTripsIndex::class);

    $page->call('setVisibility', $group, 'link');
    expect(myTripsListing($group))->toBe([['link'], [null]]);

    $page->call('setVisibility', $group, 'followers:3d')->call('setVisibility', $group, 'public');
    expect(myTripsListing($group))->toBe([['public'], [null]]);
});

it('takes a single-schedule offering by its solo key', function () {
    $host = newWizardHost();
    $one = Trip::factory()->published()->create(['host_id' => $host->id]);
    $beside = Trip::factory()->published()->create(['host_id' => $host->id]);

    Livewire::actingAs($host)->test(HostTripsIndex::class)->call('setVisibility', 'solo-'.$one->id, 'link');

    expect($one->fresh()->visibility)->toBe('link')->and($beside->fresh()->visibility)->toBe('public');
});

it('ignores a choice it does not offer', function (string $choice) {
    $host = newWizardHost();
    // followers first with a date, so that accepting any of these would change the visibility or lose the date
    $opens = now()->addDays(3);
    $group = myTripsOffering($host, 2, ['visibility' => 'followers', 'public_at' => $opens])->first()->trip_group_id;

    Livewire::actingAs($host)->test(HostTripsIndex::class)->call('setVisibility', $group, $choice);

    expect(myTripsListing($group))->toBe([['followers'], [$opens->toDateTimeString()]]);
})->with(['', 'bogus', 'followers', 'followers:', 'followers:2w', 'followers:1D', 'public:1d', 'link:3d', 'Link', ' link']);

it('ignores a group that belongs to another host', function () {
    $host = newWizardHost();
    $offering = myTripsOffering($host);
    $solo = Trip::factory()->published()->create(['host_id' => $host->id]);
    $other = newWizardHost();

    Livewire::actingAs($other)->test(HostTripsIndex::class)
        ->call('setVisibility', $offering->first()->trip_group_id, 'link')
        ->call('setVisibility', 'solo-'.$solo->id, 'followers:1w');

    expect(myTripsListing($offering->first()->trip_group_id))->toBe([['public'], [null]])
        ->and($solo->fresh()->visibility)->toBe('public');
});

it('shows the chip to the trip\'s host and to nobody else', function () {
    $this->travelTo('2026-11-26 09:00:00');
    $trip = Trip::factory()->published()->linkOnly()->create();
    $chip = fn (Trip $t) => trim(Blade::render('<x-visibility-chip :trip="$t" />', ['t' => $t]));

    // the guest first, then a signed-in stranger, then the host: actingAs() holds for the rest of the test
    expect($chip($trip))->toBe('');

    $this->actingAs(User::factory()->create());
    expect($chip($trip))->toBe('');

    $this->actingAs($trip->host);
    expect($chip($trip))->toContain('Link only')
        ->and($chip(Trip::factory()->published()->followersFirst(now()->addDays(3))->create(['host_id' => $trip->host_id])))
        ->toContain('Followers first until 29 Nov');
});

it('has no chip for a trip that everyone can find', function () {
    $open = Trip::factory()->published()->create();
    $opened = Trip::factory()->published()->followersFirst(now()->subHour())->create(['host_id' => $open->host_id]);   // window already over

    $this->actingAs($open->host);

    foreach ([$open, $opened] as $trip) {
        expect(trim(Blade::render('<x-visibility-chip :trip="$t" />', ['t' => $trip])))->toBe('');
    }
});

it('puts the chip on the trip card and the trip tile for the host only', function () {
    $trip = Trip::factory()->published()->linkOnly()->create();
    $render = fn (string $component) => Blade::render("<x-{$component} :trip=\"\$t\" />", ['t' => $trip->fresh()]);

    expect($render('trip-card'))->not->toContain('Link only')
        ->and($render('trip-tile'))->not->toContain('Link only');

    $this->actingAs($trip->host);

    expect($render('trip-card'))->toContain('Link only')
        ->and($render('trip-tile'))->toContain('Link only');
});

it('shows the chip on the host\'s own page and not to a visitor', function () {
    $host = newWizardHost();
    $trip = Trip::factory()->published()->linkOnly()->create(['host_id' => $host->id, 'title' => 'Hidden Ridge Walk']);

    $this->get(route('hosts.show', $host))->assertDontSee('Link only')->assertDontSee($trip->title);

    $this->actingAs($host)->get(route('hosts.show', $host))->assertSee('Link only')->assertSee($trip->title);
});

it('draws a chip on each schedule that is not public and an action menu on each offering', function () {
    $host = newWizardHost();
    [$first, $second] = myTripsOffering($host, 2, ['title' => 'Mount Pulag Traverse']);
    $first->update(['visibility' => 'link']);
    $solo = Trip::factory()->published()->followersFirst()->create(['host_id' => $host->id]);
    $group = $first->trip_group_id;

    Livewire::actingAs($host)->test(HostTripsIndex::class)->set('filter', 'all')
        // a chip on the schedule that is link only and on the followers-first one, none on the public one
        ->assertSeeHtml('dusk="chip-'.$first->id.'"')
        ->assertSeeHtml('dusk="chip-'.$solo->id.'"')
        ->assertDontSeeHtml('dusk="chip-'.$second->id.'"')
        // one menu per offering, named for it, resting on its prompt and offering each choice once
        ->assertSeeHtml('dusk="visibility-'.$group.'"')
        ->assertSeeHtml('dusk="visibility-solo-'.$solo->id.'"')
        ->assertSeeHtml('aria-label="Who can see it: Mount Pulag Traverse"')
        ->assertSeeHtml('<option value="" selected disabled>Who can see it</option>')
        ->assertSeeHtmlInOrder([
            '<option value="public">Everyone</option>',
            '<option value="followers:1d">Followers first for 1 day</option>',
            '<option value="followers:3d">Followers first for 3 days</option>',
            '<option value="followers:1w">Followers first for 1 week</option>',
            '<option value="link">Link only</option>',
        ])
        // and a pick reaches setVisibility with that offering's own key
        ->assertSeeHtml("setVisibility('{$group}', \$event.target.value)")
        ->assertSeeHtml("setVisibility('solo-{$solo->id}', \$event.target.value)");
});

it('changes the listing from the page', function () {
    $host = newWizardHost();
    $offering = myTripsOffering($host);

    Livewire::actingAs($host)->test(HostTripsIndex::class)
        ->assertDontSeeHtml('dusk="chip-'.$offering->first()->id.'"')
        ->call('setVisibility', $offering->first()->trip_group_id, 'link')
        ->assertSeeHtml('dusk="chip-'.$offering->first()->id.'"')
        ->assertSeeHtml('dusk="chip-'.$offering->last()->id.'"');
});

it('offers the menu on the My trips page itself', function () {
    $host = newWizardHost();
    myTripsOffering($host);

    $this->actingAs($host)->get('/host/trips')->assertOk()->assertSee('Who can see it');
});
