<?php

use App\Models\Trip;
use App\Services\ModerationService;

/*
 * A takedown is moderation's call. Wherever the host manages the trip they are told it is down and that editing
 * does not undo that, and they get no link to its dead public page.
 */

/** One of the pages where a host manages a schedule. */
function hostPageFor(string $page, Trip $trip): string
{
    return match ($page) {
        'My trips' => route('host.trips.index'),
        'the edit form' => route('host.trips.edit', $trip),
        'the roster' => route('host.roster', $trip->departure),
    };
}

it('tells the host that a trip was taken down', function (string $page) {
    $trip = publishViaWizard()->sole();
    $this->actingAs($trip->host);

    $this->get(hostPageFor($page, $trip))->assertOk()->assertDontSee('Taken down by Tarana');

    app(ModerationService::class)->unpublish($trip);

    $this->get(hostPageFor($page, $trip))->assertOk()
        ->assertSee('Taken down by Tarana')
        ->assertSee('editing it will not bring it back');
})->with(['My trips', 'the edit form', 'the roster']);

it('drops View and Share from My trips for a taken-down trip', function () {
    $trip = publishViaWizard()->sole();
    $this->actingAs($trip->host);

    // the control: they are there while the trip is up, so their absence afterwards means something
    $this->get(route('host.trips.index'))
        ->assertSee('href="'.route('trips.show', $trip).'"', false)
        ->assertSee('dusk="share-url"', false)
        ->assertSee(route('host.trips.qr', $trip), false);

    app(ModerationService::class)->unpublish($trip);

    $this->get(route('host.trips.index'))
        ->assertDontSee('/t/'.$trip->slug, false)
        ->assertDontSee('dusk="share-url"', false)
        ->assertDontSee(route('host.trips.qr', $trip), false)
        ->assertSee(route('host.trips.edit', $trip), false)           // still theirs to fix
        ->assertSee(route('host.roster', $trip->departure), false);   // and its bookings still theirs to run
});

it('drops the trip QR from the roster of a taken-down trip', function () {
    $trip = publishViaWizard()->sole();
    $this->actingAs($trip->host);
    $roster = route('host.roster', $trip->departure);

    $this->get($roster)->assertSee(route('host.trips.qr', $trip), false);

    app(ModerationService::class)->unpublish($trip);

    $this->get($roster)->assertOk()
        ->assertDontSee(route('host.trips.qr', $trip), false)
        ->assertSee(route('host.trips.edit', $trip), false);
});
