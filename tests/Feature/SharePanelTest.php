<?php

use App\Livewire\Host\Trips\Create;
use App\Livewire\Host\Trips\Index as HostTripsIndex;
use App\Models\HostProfile;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\Qr;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;

/*
 * The panel a host opens to hand a link out: the address to copy, the phone's own share sheet, Facebook and a QR.
 * It is the host's tool, so each page that carries one shows it to that host and to nobody else.
 */

/** A host with a page of their own, at /{handle}. Pass null for one who has not claimed a handle. */
function shareHost(?string $handle = 'trailco'): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => $handle, 'name' => 'Trail Collective']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

function shareTrip(User $host): Trip
{
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Sagada Echo Valley Hike']);
    TripDeparture::factory()->for($trip)->create(['starts_at' => now()->addWeeks(3)]);

    return $trip;
}

it('renders the link, a Facebook share, the phone share hook and a QR only when given one', function () {
    $html = Blade::render('<x-share-panel url="https://tarana.test/t/x" title="X" qr="https://tarana.test/qr" />');
    expect($html)->toContain('value="https://tarana.test/t/x"')
        ->toContain('https://www.facebook.com/sharer/sharer.php?u='.urlencode('https://tarana.test/t/x'))
        ->toContain('navigator.share')->toContain('https://tarana.test/qr')
        ->toContain('wire:key="share-');
    expect(Blade::render('<x-share-panel url="https://tarana.test/t/x" title="X" />'))->not->toContain('download');
});

it('closes itself when keyboard focus moves outside it, so it never sits over the tab stops after it', function () {
    expect(Blade::render('<x-share-panel url="https://tarana.test/t/x" title="X" />'))
        ->toContain('@focusin.window="if (open && ! $el.contains($event.target)) open = false"');
});

it('says so when the share sheet fails, and stays quiet when it is only closed', function () {
    expect(Blade::render('<x-share-panel url="https://tarana.test/t/x" title="X" />'))
        ->toContain("e.name !== 'AbortError'")
        ->toContain('Could not open sharing. Copy the link instead.');
});

it('keeps a quote or a tag in a title or a link inside its attribute and its script', function () {
    $html = Blade::render(
        '<x-share-panel :url="$url" :title="$title" :qr="$url" />',
        ['url' => 'https://tarana.test/t/x?a=1&b="2"', 'title' => 'Pulag "Sea of Clouds" & <b>Beyond</b> \'25'],
    );

    // Nothing the host typed arrives as markup, and none of it can end the quote the Alpine state sits in.
    expect($html)->not->toContain('<b>Beyond</b>')
        ->not->toContain('b="2"')
        ->not->toContain('Clouds" &')
        ->toContain('Pulag &quot;Sea of Clouds&quot; &amp; &lt;b&gt;Beyond&lt;/b&gt;')
        ->toContain('u='.urlencode('https://tarana.test/t/x?a=1&b="2"'));

    // The Alpine state is the one place a browser turns entities back into the characters it then runs as script: an
    // &#039; there is a bare ' and ends the string. @js writes each quote, ampersand and bracket as a \uXXXX escape instead.
    preg_match('/x-data="([^"]*)"/', $html, $state);
    expect($state[1])
        ->toContain('title: \'Pulag \u0022Sea of Clouds\u0022 \u0026 \u003Cb\u003EBeyond\u003C\/b\u003E \u002725\'')
        ->toContain('url: \'https:\/\/tarana.test\/t\/x?a=1\u0026b=\u00222\u0022\'')
        ->not->toContain('title: \'Pulag &quot;')
        ->not->toMatch('/&(?:quot|#0?39|amp|lt|gt);/');
});

it('shows the trip page share panel to its host and to nobody else', function () {
    $trip = shareTrip(shareHost());

    // a guest first: once a test has acted as someone, every request after it is theirs
    $this->get(route('trips.show', $trip))->assertDontSee('dusk="share-url"', false);
    $this->actingAs(User::factory()->create(['role' => 'host']))->get(route('trips.show', $trip))->assertDontSee('dusk="share-url"', false);
    $this->actingAs($trip->host)->get(route('trips.show', $trip))->assertSee('dusk="share-url"', false);
});

it('opens the panel for the host right after publishing', function () {
    $created = publishViaWizard()->first();
    expect(session('just_published'))->toBe($created->id);
    $this->actingAs($created->host)->withSession(['just_published' => $created->id])
        ->get(route('trips.show', $created))->assertSee('open: true', false);
});

it('opens the panel for the first of the schedules a host just published', function () {
    [$first, $second] = publishViaWizard(dates: 2)->all();

    // the redirect lands on the first one, so that is the one that is meant
    expect(session('just_published'))->toBe($first->id);
    $this->actingAs($second->host)->withSession(['just_published' => $first->id])
        ->get(route('trips.show', $second))->assertSee('dusk="share-url"', false)->assertDontSee('open: true', false);
});

it('leaves the panel closed on an ordinary visit and after an edit', function () {
    $trip = publishViaWizard()->sole();
    session()->forget('just_published');

    $this->actingAs($trip->host)->get(route('trips.show', $trip))->assertSee('dusk="share-url"', false)->assertDontSee('open: true', false);

    // saving a change to a trip that has been out for weeks is not a launch
    Livewire::actingAs($trip->host)->test(Create::class, ['trip' => $trip])
        ->call('publish')
        ->assertHasNoErrors()
        ->assertRedirect(route('trips.show', $trip));
    expect(session('just_published'))->toBeNull();
});

it('shows the host page share panel to its host only', function () {
    $host = shareHost();

    $this->get(route('hosts.handle', $host))->assertDontSee('dusk="share-url"', false);
    $this->actingAs(User::factory()->create(['role' => 'joiner']))->get(route('hosts.handle', $host))->assertDontSee('dusk="share-url"', false);
    $this->actingAs($host)->get(route('hosts.handle', $host))->assertSee('dusk="share-url"', false);
});

it('shows a share panel on Your page once the host has a handle', function () {
    $host = shareHost();

    $this->actingAs($host)->get(route('host.profile'))->assertOk()
        ->assertSee('dusk="share-url"', false)
        ->assertSee('value="'.route('hosts.handle', $host).'"', false)
        ->assertSee(route('host.profile.qr'));
});

it('shows no host-page panel or QR for a host without a handle', function () {
    $noHandleHost = shareHost(null);

    $this->actingAs($noHandleHost)->get(route('host.profile'))->assertDontSee('dusk="share-url"', false);
    $this->actingAs($noHandleHost)->get(route('host.profile.qr'))->assertNotFound();
});

it('shows a share panel on every schedule in My trips', function () {
    $host = shareHost();
    $first = shareTrip($host);
    $second = shareTrip($host);

    $page = Livewire::actingAs($host)->test(HostTripsIndex::class);

    foreach ([$first, $second] as $trip) {
        $page->assertSeeHtml('value="'.route('trips.show', $trip).'"')->assertSeeHtml('href="'.route('host.trips.qr', $trip).'"');
    }
});

it('serves the host page QR as a PNG to signed-in hosts only', function () {
    $host = shareHost();

    $this->get(route('host.profile.qr'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create(['role' => 'joiner']))->get(route('host.profile.qr'))->assertForbidden();

    $response = $this->actingAs($host)->get(route('host.profile.qr'))->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->headers->get('Content-Disposition'))->toBe('inline; filename="tarana-trailco-qr.png"')
        // the same picture as any QR of that address: the page it points at is the host's own
        ->and($response->getContent())->toBe(Qr::png(route('hosts.handle', $host))->getString());
});

it('still serves a trip QR as it always has, now drawn by the shared builder', function () {
    $trip = shareTrip(shareHost());

    $response = $this->actingAs($trip->host)->get(route('host.trips.qr', $trip))->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->headers->get('Content-Disposition'))->toBe('inline; filename="tarana-'.$trip->slug.'-qr.png"')
        ->and($response->getContent())->toBe(Qr::png(route('trips.show', $trip))->getString());
});

it('draws every QR the same way: pine on white, a 600px code in a 24px margin', function () {
    $image = imagecreatefromstring(Qr::png('https://tarana.test/t/x')->getString());
    expect([imagesx($image), imagesy($image)])->toBe([648, 648]);

    // The writer keeps a two-colour palette, so the colours come back a shade off (pine as 44,58,36). Close is the claim.
    $colours = collect(range(0, imagecolorstotal($image) - 1))
        ->map(fn ($i) => array_values(array_slice(imagecolorsforindex($image, $i), 0, 3)))
        ->sortBy(fn ($rgb) => array_sum($rgb))
        ->values();
    $near = fn (array $rgb, array $wanted) => collect($rgb)->zip($wanted)->every(fn ($pair) => abs($pair[0] - $pair[1]) <= 6);

    expect($colours)->toHaveCount(2)
        ->and($near($colours[0], [44, 56, 38]))->toBeTrue()
        ->and($near($colours[1], [255, 255, 255]))->toBeTrue();
});
