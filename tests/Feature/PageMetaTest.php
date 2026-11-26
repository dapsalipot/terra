<?php

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\PageMeta;
use Illuminate\Support\Facades\Storage;

/*
 * What a link looks like once it is pasted into Facebook, Messenger or a group chat: the tags the layout
 * writes for a page, and the one card every page without a photograph of its own falls back to.
 */

function metaHost(string $handle = 'ridgeline', string $name = 'Ridgeline', array $profile = []): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => $handle, 'name' => $name]);
    HostProfile::factory()->for($host)->create($profile + ['currency' => 'PHP']);

    return $host;
}

/** What a stored cover is served as, in full: the tags need an address a crawler can fetch. */
function metaCover(string $name): string
{
    return url(Storage::disk('public')->url("covers/{$name}.jpg"));
}

it('previews a trip with its title, host, price and an absolute image', function () {
    $this->travelTo('2026-12-01 10:00:00');
    $trip = Trip::factory()->published()->create([
        'host_id' => metaHost()->id,
        'place_id' => Place::factory()->create(['name' => 'Mount Pulag', 'country' => 'Philippines'])->id,
        'title' => 'Pulag Traverse',
        'price' => 2500,
    ]);
    TripDeparture::factory()->for($trip)->create(['starts_at' => '2026-12-14 06:00:00', 'ends_at' => '2026-12-16 18:00:00', 'capacity' => 12]);

    $this->get(route('trips.show', $trip))
        ->assertSee('<meta property="og:title" content="Pulag Traverse · Ridgeline">', false)
        ->assertSee('<meta property="og:description" content="Mount Pulag, Philippines · Dec 14-16 · from PHP 2,500 per person · 12 spots left · led by Ridgeline">', false)
        ->assertSee('<meta property="og:url" content="'.route('trips.show', $trip).'">', false)
        ->assertSee('<link rel="canonical" href="'.route('trips.show', $trip).'">', false)
        ->assertSee('<meta property="og:image" content="'.$trip->cover_url.'">', false)
        ->assertSee('<meta property="og:site_name" content="Tarana">', false)
        ->assertSee('<meta property="og:type" content="website">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<title>Pulag Traverse · Ridgeline</title>', false)
        ->assertDontSee('noindex', false);

    expect(PageMeta::trip($trip)['image'])->toStartWith('http');
});

it('previews a trip with no dates yet without leaving a gap in the description', function () {
    $trip = Trip::factory()->published()->create([
        'host_id' => metaHost()->id,
        'place_id' => Place::factory()->create(['name' => 'Mount Pulag', 'country' => 'Philippines'])->id,
        'price' => 2500,
    ]);

    expect(PageMeta::trip($trip)['description'])->toBe('Mount Pulag, Philippines · from PHP 2,500 per person · led by Ridgeline');
});

it('says how many spots are left, in the singular for one and Full for none', function () {
    $this->travelTo('2026-12-01 10:00:00');
    $trip = Trip::factory()->published()->create([
        'host_id' => metaHost()->id,
        'place_id' => Place::factory()->create(['name' => 'Mount Pulag', 'country' => 'Philippines'])->id,
        'price' => 2500,
    ]);
    $departure = TripDeparture::factory()->for($trip)->create(['starts_at' => '2026-12-14 06:00:00', 'ends_at' => '2026-12-16 18:00:00', 'capacity' => 4]);
    $says = fn (string $seats) => "Mount Pulag, Philippines · Dec 14-16 · from PHP 2,500 per person · {$seats} · led by Ridgeline";
    $hold = fn (int $party, string $status) => Reservation::factory()->create(['trip_departure_id' => $departure->id, 'party_size' => $party, 'status' => $status]);

    expect(PageMeta::trip($trip->fresh())['description'])->toBe($says('4 spots left'));

    $hold(3, 'confirmed');
    expect(PageMeta::trip($trip->fresh())['description'])->toBe($says('1 spot left'));

    $hold(1, 'pending');
    expect(PageMeta::trip($trip->fresh())['description'])->toBe($says('Full'));
});

it('leaves the dates and the spots out of a trip with nothing open to join', function () {
    $this->travelTo('2026-12-01 10:00:00');
    $trip = Trip::factory()->published()->create([
        'host_id' => metaHost()->id,
        'place_id' => Place::factory()->create(['name' => 'Mount Pulag', 'country' => 'Philippines'])->id,
        'price' => 2500,
    ]);
    TripDeparture::factory()->for($trip)->create(['starts_at' => '2026-11-20 06:00:00', 'capacity' => 8]);
    TripDeparture::factory()->for($trip)->create(['starts_at' => '2026-12-14 06:00:00', 'capacity' => 8, 'status' => 'cancelled']);
    TripDeparture::factory()->for($trip)->create(['starts_at' => '2026-12-20 06:00:00', 'capacity' => 8, 'status' => 'full']);

    // One that already left, one called off, one with no room left: none of them is something to join.
    expect(PageMeta::trip($trip)['description'])->toBe('Mount Pulag, Philippines · from PHP 2,500 per person · led by Ridgeline');
});

it('writes a trip that crosses a month, or runs a single day, as a date range should read', function () {
    $this->travelTo('2026-12-01 10:00:00');
    $host = metaHost();
    $span = function (string $starts, ?string $ends) use ($host) {
        $trip = Trip::factory()->published()->create(['host_id' => $host->id]);
        TripDeparture::factory()->for($trip)->create(['starts_at' => $starts, 'ends_at' => $ends]);

        return PageMeta::trip($trip)['description'];
    };

    expect($span('2026-12-30 06:00:00', '2027-01-02 18:00:00'))->toContain(' · Dec 30-Jan 2 · ')
        ->and($span('2026-12-14 06:00:00', '2026-12-14 18:00:00'))->toContain(' · Dec 14 · ')
        ->and($span('2026-12-14 06:00:00', null))->toContain(' · Dec 14 · ');
});

it('escapes quotes and ampersands in preview tags', function () {
    $trip = Trip::factory()->published()->create(['host_id' => metaHost()->id, 'title' => 'Pulag "Sea of Clouds" & Beyond']);

    // The title is on the page itself too, so the tag is what has to be looked at, not the page.
    $this->get(route('trips.show', $trip))
        ->assertSee('<meta property="og:title" content="Pulag &quot;Sea of Clouds&quot; &amp; Beyond · Ridgeline">', false)
        ->assertDontSee('content="Pulag "', false);
});

it('gives an absolute image for an uploaded cover and a stock cover', function () {
    $host = metaHost();
    $uploaded = Trip::factory()->published()->create(['host_id' => $host->id, 'cover_photo' => 'covers/summit.jpg']);
    $stock = Trip::factory()->published()->create(['host_id' => $host->id, 'cover_photo' => null]);

    expect(PageMeta::trip($uploaded)['image'])->toBe(metaCover('summit'))->toStartWith('http')
        ->and(PageMeta::trip($stock)['image'])->toBe($stock->cover_url)->toStartWith('http');
});

it('marks any trip that is not public right now noindex', function () {
    $host = metaHost();
    $link = Trip::factory()->published()->linkOnly()->create(['host_id' => $host->id]);
    $early = Trip::factory()->published()->followersFirst(now()->addDays(3))->create(['host_id' => $host->id]);
    $opened = Trip::factory()->published()->followersFirst(now()->subDay())->create(['host_id' => $host->id]);

    foreach ([$link, $early] as $trip) {
        // Unlisted is not unshareable: the preview is still there, only the search engines are told to leave it.
        $this->get(route('trips.show', $trip))
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('<meta property="og:title" content="'.e($trip->title).' · Ridgeline">', false);
        expect(PageMeta::trip($trip)['noindex'])->toBeTrue();
    }

    $this->get(route('trips.show', $opened))->assertDontSee('noindex', false);
    expect(PageMeta::trip($opened)['noindex'])->toBeFalse();
});

it('previews a host page and falls back to the default card', function () {
    $host = metaHost(profile: ['tagline' => 'Slow hikes, big views']);
    $bare = metaHost('trailco', 'Trail Co');

    $this->get(route('hosts.handle', $host))
        ->assertSee('<meta property="og:title" content="Ridgeline on Tarana">', false)
        ->assertSee('<meta property="og:description" content="Slow hikes, big views">', false)
        ->assertSee('<meta property="og:url" content="'.route('hosts.handle', $host).'">', false)
        ->assertSee('<link rel="canonical" href="'.route('hosts.handle', $host).'">', false)
        ->assertSee('<title>Ridgeline on Tarana</title>', false)
        ->assertDontSee('noindex', false);

    expect(PageMeta::host($bare))->toBe([
        'title' => 'Trail Co on Tarana',
        'description' => 'Group trips led by Trail Co',
        'image' => asset('images/og-card.png'),
        'url' => route('hosts.handle', $bare),
        'noindex' => false,
    ]);
});

it("uses a host's own cover first, then the cover of the next trip anyone can find, then the default card", function () {
    $host = metaHost();
    $unlisted = Trip::factory()->published()->linkOnly()->create(['host_id' => $host->id, 'cover_photo' => 'covers/unlisted.jpg']);
    $early = Trip::factory()->published()->followersFirst(now()->addDays(3))->create(['host_id' => $host->id, 'cover_photo' => 'covers/early.jpg']);
    $gone = Trip::factory()->published()->create(['host_id' => $host->id, 'cover_photo' => 'covers/gone.jpg']);
    $calledOff = Trip::factory()->published()->create(['host_id' => $host->id, 'cover_photo' => 'covers/called-off.jpg']);
    $later = Trip::factory()->published()->create(['host_id' => $host->id, 'cover_photo' => 'covers/later.jpg']);
    $soon = Trip::factory()->published()->create(['host_id' => $host->id, 'cover_photo' => 'covers/soon.jpg']);
    $follower = User::factory()->create();
    $follower->follow($host);

    // With nothing anyone can join yet (a trip that left, one that was called off, two that are not for everyone), the default card.
    foreach ([$unlisted, $early] as $trip) {
        TripDeparture::factory()->for($trip)->create(['starts_at' => now()->addDays(2)]);
    }
    TripDeparture::factory()->for($gone)->create(['starts_at' => now()->subWeek()]);
    TripDeparture::factory()->for($calledOff)->create(['starts_at' => now()->addDays(2), 'status' => 'cancelled']);
    expect(PageMeta::host($host)['image'])->toBe(asset('images/og-card.png'));

    // The two that anyone can find, the sooner of them lending its cover. The trips that are listed to
    // some people only leave the preview alone even though they depart first: the link is the same for
    // everyone, so what it shows must not depend on who happens to fetch it.
    TripDeparture::factory()->for($later)->create(['starts_at' => now()->addWeeks(3)]);
    TripDeparture::factory()->for($soon)->create(['starts_at' => now()->addWeek()]);
    $page = route('hosts.handle', $host);

    expect(PageMeta::host($host)['image'])->toBe(metaCover('soon'));
    $this->get($page)->assertSee('<meta property="og:image" content="'.metaCover('soon').'">', false);
    $this->actingAs($follower)->get($page)->assertSee('<meta property="og:image" content="'.metaCover('soon').'">', false);

    // Their own cover beats all of it.
    $host->hostProfile->update(['cover_photo' => 'covers/own.jpg']);
    expect(PageMeta::host($host->fresh())['image'])->toBe(metaCover('own'));
});

it('gives every other page the default card', function () {
    $this->get('/')
        ->assertSee('<meta property="og:title" content="Tarana">', false)
        ->assertSee('<meta property="og:image" content="'.asset('images/og-card.png').'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('<title>Tarana</title>', false)
        ->assertDontSee('noindex', false);

    // A page answers to the address it was asked for, filters and page number included: a shared search keeps
    // its search, and page 2 is not taken for page 1.
    $explore = route('trips.index', ['category' => 'hike']);

    $this->get($explore)
        ->assertSee('<link rel="canonical" href="'.$explore.'">', false)
        ->assertSee('<meta property="og:url" content="'.$explore.'">', false)
        ->assertSee('<meta property="og:image" content="'.asset('images/og-card.png').'">', false);
});

it('keeps a query string from breaking out of the tags it is echoed into', function () {
    $this->get('/trips?search='.urlencode('"><script>alert(1)</script>'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)', false);
});

it('ships the default card at the size every network wants', function () {
    $card = public_path('images/og-card.png');

    expect($card)->toBeFile()
        ->and(array_slice(getimagesize($card), 0, 3))->toBe([1200, 630, IMAGETYPE_PNG]);
});
