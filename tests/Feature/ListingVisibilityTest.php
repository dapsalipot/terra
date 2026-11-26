<?php

use App\Livewire\Home;
use App\Livewire\Hosts\Index as HostsIndex;
use App\Livewire\Hosts\Show as HostsShow;
use App\Livewire\Joiner\Dashboard as JoinerDashboard;
use App\Livewire\Trips\Index as TripsIndex;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Post;
use App\Models\Review;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\HostCards;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * Listing control decides who is shown a trip, never who may open one: every discovery surface lists by
 * viewer, and the pages reached by a link do not care.
 *
 * Both Livewire::actingAs() and $this->actingAs() stay signed in for the rest of the test, so each test
 * looks at the guest first, then the follower, then the host.
 */

function visibilityHost(string $handle = 'ridgeline'): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => $handle, 'name' => ucfirst($handle)]);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

/** A published trip of `$host`: listed to everyone (`public`), to their followers first (`followers`) or to nobody (`link`). */
function visibilityTrip(User $host, string $listing = 'public', string $category = 'hike', array $attrs = []): Trip
{
    $trip = Trip::factory()->published();

    $trip = match ($listing) {
        'followers' => $trip->followersFirst(),
        'link' => $trip->linkOnly(),
        default => $trip,
    };

    return $trip->create($attrs + [
        'host_id' => $host->id,
        'place_id' => Place::factory()->create(['category' => $category])->id,
        'title' => ucfirst($listing).' Zephyr '.$host->handle,
    ]);
}

function visibilityFollower(User $host): User
{
    $follower = User::factory()->create();
    $follower->follow($host);

    return $follower;
}

/** Assert which trips a rendered host page names, by title and by link, and which it keeps to itself. */
function visibilityAssertNames($page, array $named, array $unnamed): void
{
    foreach ($named as $trip) {
        $page->assertSee($trip->title)->assertSee(route('trips.show', $trip));
    }

    foreach ($unnamed as $trip) {
        $page->assertDontSee($trip->title)->assertDontSee(route('trips.show', $trip));
    }
}

it('keeps link-only trips out of Explore and shows followers-first ones to followers only', function () {
    $host = visibilityHost();
    $open = visibilityTrip($host);
    $early = visibilityTrip($host, 'followers');
    $link = visibilityTrip($host, 'link');
    $follower = visibilityFollower($host);

    $this->get('/trips')->assertSee($open->title)->assertDontSee($early->title)->assertDontSee($link->title);
    $this->actingAs(User::factory()->create())->get('/trips')->assertSee($open->title)->assertDontSee($early->title);
    $this->actingAs($follower)->get('/trips')->assertSee($early->title)->assertDontSee($link->title);
});

it('keeps a host\'s own unlisted trips out of Explore and Home, and on their own page', function () {
    $host = visibilityHost();
    $open = visibilityTrip($host);
    $early = visibilityTrip($host, 'followers');
    $link = visibilityTrip($host, 'link');
    $own = route('hosts.handle', $host);

    // The host is signed in for every request below. Explore and Home are where trips are found, and a host finds
    // their own there like anyone else does: the one everyone can find, and not the two they chose to hold back.
    $this->actingAs($host);

    foreach (['/trips', '/'] as $page) {
        $this->get($page)->assertOk()->assertSee($open->title)->assertDontSee($early->title)->assertDontSee($link->title);
    }

    // Their own page is where all of them are, so that choosing to hold a trip back does not lose it.
    $this->get($own)->assertSee($open->title)->assertSee($early->title)->assertSee($link->title);
});

it('lists a mixed-visibility offering once, counting only its listed schedules', function () {
    $host = visibilityHost();
    $group = (string) Str::uuid();
    $open = visibilityTrip($host, attrs: ['trip_group_id' => $group]);
    visibilityTrip($host, 'link', attrs: ['trip_group_id' => $group]);

    Livewire::test(TripsIndex::class)
        ->assertViewHas('trips', fn ($p) => $p->count() === 1 && $p->first()->is($open) && $p->first()->schedule_count === 1);
});

it('leaves a host with only unlisted trips out of Home\'s top hosts', function () {
    $listedHost = visibilityHost('ridgeline');
    visibilityTrip($listedHost);
    $unlistedOnlyHost = visibilityHost('quietco');
    visibilityTrip($unlistedOnlyHost, 'link');

    Livewire::test(Home::class)->assertViewHas('hosts', fn ($h) => $h->contains('id', $listedHost->id) && $h->doesntContain('id', $unlistedOnlyHost->id));
});

it('shows a follower what a guest has to wait for on Home: trips, trip types and top hosts', function () {
    $host = visibilityHost();
    $early = visibilityTrip($host, 'followers', 'surf');
    $follower = visibilityFollower($host);

    $guest = Livewire::test(Home::class);
    expect($guest->viewData('soon')->pluck('id'))->not->toContain($early->id)
        ->and($guest->viewData('tripTypes')->keys()->all())->not->toContain('surf')
        ->and($guest->viewData('hosts')->pluck('id'))->not->toContain($host->id);

    $member = Livewire::actingAs($follower)->test(Home::class);
    expect($member->viewData('soon')->pluck('id'))->toContain($early->id)
        ->and($member->viewData('tripTypes')->keys()->all())->toContain('surf')
        ->and($member->viewData('hosts')->pluck('id'))->toContain($host->id);
});

it('shows the same headline trip count to a follower and a guest', function () {
    $host = visibilityHost();
    visibilityTrip($host);
    visibilityTrip($host, 'followers');
    visibilityTrip($host, 'link');
    $follower = visibilityFollower($host);

    // The guest first: Livewire::actingAs() keeps the follower signed in for every render after it.
    $guest = Livewire::test(Home::class)->viewData('stats')['trips'];
    $member = Livewire::actingAs($follower)->test(Home::class)->viewData('stats')['trips'];

    // One trip is listed to everyone. Counting the follower's own listing would make it two.
    expect($member)->toBe($guest)->and($guest)->toBe(1);
});

it('counts only listed trips on directory cards and filters categories by them', function () {
    $host = visibilityHost();
    visibilityTrip($host, 'public', 'surf');
    visibilityTrip($host, 'link', 'hike');   // the host's only hike

    Livewire::test(HostsIndex::class)
        ->assertViewHas('hosts', fn ($p) => $p->first()->published_trips === 1)
        ->assertViewHas('categoriesByHost', fn ($kinds) => collect($kinds[$host->id])->pluck('key')->all() === ['surf']);
    Livewire::test(HostsIndex::class)->set('category', 'hike')->assertViewHas('hosts', fn ($p) => $p->isEmpty());
});

it('counts a followed host\'s followers-first trips on their directory card, and lets the category filter find them', function () {
    $host = visibilityHost();
    visibilityTrip($host, 'public', 'surf');
    visibilityTrip($host, 'followers', 'island');
    visibilityTrip($host, 'link', 'hike');
    $follower = visibilityFollower($host);

    Livewire::test(HostsIndex::class)->set('category', 'island')->assertViewHas('hosts', fn ($p) => $p->isEmpty());

    Livewire::actingAs($follower)->test(HostsIndex::class)
        ->assertViewHas('hosts', fn ($p) => $p->first()->published_trips === 2)
        ->assertViewHas('categoriesByHost', fn ($kinds) => collect($kinds[$host->id])->pluck('key')->all() === ['island', 'surf'])
        ->set('category', 'island')->assertViewHas('hosts', fn ($p) => $p->count() === 1)
        ->set('category', 'hike')->assertViewHas('hosts', fn ($p) => $p->isEmpty());
});

it('ignores unlisted trips when picking a host card\'s next departure', function () {
    $host = visibilityHost();
    $link = visibilityTrip($host, 'link');
    TripDeparture::factory()->for($link)->create(['starts_at' => now()->addWeek()]);

    expect(HostCards::extras([$host->id], null)['next'])->toBe([]);
});

it('picks a host card\'s next departure from the trips its viewer can find', function () {
    $host = visibilityHost();
    $follower = visibilityFollower($host);
    $link = visibilityTrip($host, 'link');
    $early = visibilityTrip($host, 'followers');
    $open = visibilityTrip($host);
    TripDeparture::factory()->for($link)->create(['starts_at' => now()->addWeek()]);
    $earlyDeparture = TripDeparture::factory()->for($early)->create(['starts_at' => now()->addWeeks(2)]);
    $openDeparture = TripDeparture::factory()->for($open)->create(['starts_at' => now()->addWeeks(3)]);

    expect(HostCards::extras([$host->id], null)['next'][$host->id]->id)->toBe($openDeparture->id)
        ->and(HostCards::extras([$host->id], $follower)['next'][$host->id]->id)->toBe($earlyDeparture->id);
});

it('shows a host page by viewer', function () {
    $host = visibilityHost();
    $open = visibilityTrip($host);
    $early = visibilityTrip($host, 'followers');
    $link = visibilityTrip($host, 'link');
    $follower = visibilityFollower($host);
    $url = route('hosts.handle', $host);

    $this->get($url)->assertSee($open->title)->assertDontSee($early->title)->assertDontSee($link->title);
    $this->actingAs($follower)->get($url)->assertSee($early->title)->assertDontSee($link->title);
    $this->actingAs($host)->get($url)->assertSee($early->title)->assertSee($link->title);
});

it('lists a host\'s next departures by viewer too', function () {
    $host = visibilityHost();
    $open = visibilityTrip($host);
    $early = visibilityTrip($host, 'followers');
    $link = visibilityTrip($host, 'link');
    $follower = visibilityFollower($host);
    foreach ([$open, $early, $link] as $trip) {
        TripDeparture::factory()->for($trip)->create();
    }
    $coming = fn () => Livewire::test(HostsShow::class, ['user' => $host])->viewData('upcoming')->pluck('trip_id')->all();

    $guest = $coming();
    Livewire::actingAs($follower);
    $member = $coming();
    Livewire::actingAs($host);
    $owner = $coming();

    expect($guest)->toEqualCanonicalizing([$open->id])
        ->and($member)->toEqualCanonicalizing([$open->id, $early->id])
        ->and($owner)->toEqualCanonicalizing([$open->id, $early->id, $link->id]);
});

it('keeps an unlisted trip\'s name and link off a host\'s reviews while the review itself stays', function () {
    $host = visibilityHost();
    $open = visibilityTrip($host);
    $early = visibilityTrip($host, 'followers');
    $link = visibilityTrip($host, 'link');
    $follower = visibilityFollower($host);
    foreach ([[$open, 'open'], [$early, 'early'], [$link, 'link']] as [$trip, $word]) {
        Review::factory()->create(['reviewable_id' => $trip->id, 'body' => "Fine day out on the {$word} one."]);
    }
    // Every review is read by everyone; what differs is which trips it is allowed to name.
    $page = fn () => Livewire::test(HostsShow::class, ['user' => $host])->set('tab', 'reviews')
        ->assertSee('Fine day out on the open one.')->assertSee('Fine day out on the early one.')->assertSee('Fine day out on the link one.');

    visibilityAssertNames($page(), [$open], [$early, $link]);
    Livewire::actingAs(User::factory()->create());
    visibilityAssertNames($page(), [$open], [$early, $link]);
    Livewire::actingAs($follower);
    visibilityAssertNames($page(), [$open, $early], [$link]);
    Livewire::actingAs($host);
    visibilityAssertNames($page(), [$open, $early, $link], []);
});

it('keeps an unlisted trip\'s name and link off a host\'s wall tags while the post itself stays', function () {
    $host = visibilityHost();
    $open = visibilityTrip($host);
    $early = visibilityTrip($host, 'followers');
    $link = visibilityTrip($host, 'link');
    $follower = visibilityFollower($host);
    foreach ([[$open, 'open'], [$early, 'early'], [$link, 'link']] as [$trip, $word]) {
        Post::factory()->create(['host_id' => $host->id, 'trip_id' => $trip->id, 'body' => "Out on the {$word} one."]);
    }
    $page = fn () => Livewire::test(HostsShow::class, ['user' => $host])->set('tab', 'wall')
        ->assertSee('Out on the open one.')->assertSee('Out on the early one.')->assertSee('Out on the link one.');

    visibilityAssertNames($page(), [$open], [$early, $link]);
    Livewire::actingAs(User::factory()->create());
    visibilityAssertNames($page(), [$open], [$early, $link]);
    Livewire::actingAs($follower);
    visibilityAssertNames($page(), [$open, $early], [$link]);
    Livewire::actingAs($host);
    visibilityAssertNames($page(), [$open, $early, $link], []);
});

it('counts only the trips a joiner can find on the dashboard calendar', function () {
    $host = visibilityHost();
    $day = now()->addMonth()->startOfMonth()->addDays(14);
    foreach (['public', 'followers', 'link'] as $listing) {
        TripDeparture::factory()->for(visibilityTrip($host, $listing))->create(['starts_at' => $day, 'capacity' => 8]);
    }
    $follower = visibilityFollower($host);
    $stranger = User::factory()->create(['role' => 'joiner']);

    $available = fn (User $joiner) => Livewire::actingAs($joiner)->test(JoinerDashboard::class)
        ->set('month', $day->format('Y-m'))
        ->viewData('weeks')->flatten(1)
        ->firstWhere(fn ($d) => $d['date']->toDateString() === $day->toDateString())['available'];

    // The public trip for everyone, the followers-first one as well for its host's follower, the link-only one never.
    expect($available($stranger))->toBe(1)
        ->and($available($follower))->toBe(2);
});

it('offers only the trips a joiner can find when they tap a free day', function () {
    $host = visibilityHost();
    $day = now()->addMonth()->startOfMonth()->addDays(14);
    $open = visibilityTrip($host);
    $early = visibilityTrip($host, 'followers');
    $link = visibilityTrip($host, 'link');
    foreach ([$open, $early, $link] as $trip) {
        TripDeparture::factory()->for($trip)->create(['starts_at' => $day, 'capacity' => 8]);
    }
    $follower = visibilityFollower($host);
    $stranger = User::factory()->create(['role' => 'joiner']);

    $offered = fn (User $joiner) => Livewire::actingAs($joiner)->test(JoinerDashboard::class)
        ->set('month', $day->format('Y-m'))
        ->call('selectDate', $day->toDateString())
        ->assertSeeHtml('dusk="cal-free-panel"')
        ->assertSee($open->title)
        ->viewData('availableOnDate')->pluck('trip_id')->all();

    expect($offered($stranger))->toEqualCanonicalizing([$open->id])
        ->and($offered($follower))->toEqualCanonicalizing([$open->id, $early->id]);
});

it('still opens a link-only trip by link', function () {
    $host = visibilityHost();
    $link = visibilityTrip($host, 'link');
    TripDeparture::factory()->for($link)->create();

    $this->get(route('trips.show', $link))->assertOk();
    $this->get(route('trips.join', $link))->assertOk();
    $this->actingAs($host)->get(route('host.trips.qr', $link))->assertOk();
});
