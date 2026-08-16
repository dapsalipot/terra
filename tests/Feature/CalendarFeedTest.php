<?php

use App\Livewire\Joiner\Dashboard;
use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\Ics;

function calBooking(array $attrs = [], array $tripAttrs = []): Reservation
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'rl', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(array_merge([
        'host_id' => $host->id, 'title' => 'Mount Pulag Traverse', 'meetup_point' => 'Baguio terminal',
    ], $tripAttrs));
    $departure = TripDeparture::factory()->for($trip)->create([
        'capacity' => 10,
        'starts_at' => now()->addWeek()->setTime(5, 0),
        'ends_at' => now()->addWeek()->addDay()->setTime(16, 0),
    ]);

    return Reservation::factory()->for($departure, 'departure')->create(array_merge([
        'status' => 'confirmed', 'confirmed_at' => now(), 'party_size' => 1,
    ], $attrs));
}

it('builds a calendar a client can actually parse', function () {
    $r = calBooking();

    $ics = Ics::single($r->fresh(), 'Terra');

    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->and($ics)->toContain('VERSION:2.0')
        ->and($ics)->toContain('BEGIN:VEVENT')
        ->and($ics)->toContain('END:VEVENT')
        ->and($ics)->toContain('END:VCALENDAR')
        // CRLF, not LF. Clients are stricter about this than anything else in the format.
        ->and($ics)->toContain("\r\n")
        ->and($ics)->not->toContain("\n\n");
});

it('keeps one stable id per booking so a re-poll updates instead of duplicating', function () {
    $r = calBooking();

    expect(Ics::single($r->fresh(), 'Terra'))->toContain('UID:booking-'.$r->public_token.'@terra');
});

it('leaves a cancelled booking in the feed, marked cancelled', function () {
    // Dropping it just leaves the old event sitting in somebody's calendar forever.
    $r = calBooking(['status' => 'cancelled', 'cancelled_at' => now()]);

    $ics = Ics::single($r->fresh(), 'Terra');

    expect($ics)->toContain('STATUS:CANCELLED')
        ->and($ics)->toContain('UID:booking-'.$r->public_token.'@terra');
});

it('escapes the characters that would otherwise break a line', function () {
    $r = calBooking([], ['title' => 'Pulag; Ambangeg, Day-hike \ Traverse']);

    $ics = Ics::single($r->fresh(), 'Terra');

    // Unescaped, the semicolon and comma would end the property value early and the rest of the
    // title would be read as parameters.
    expect($ics)->toContain('Pulag\; Ambangeg\\, Day-hike \\\\ Traverse');
});

it('folds a long line rather than emitting it whole', function () {
    $r = calBooking([], ['title' => str_repeat('Mount Pulag Sunrise Traverse ', 8)]);

    $ics = Ics::single($r->fresh(), 'Terra');

    foreach (explode("\r\n", $ics) as $line) {
        // RFC 5545 counts octets, and a client is entitled to reject anything longer.
        expect(strlen($line))->toBeLessThanOrEqual(75);
    }

    // Folded, not truncated: the continuation marker is a leading space.
    expect($ics)->toContain("\r\n ");
});

it('serves a joiner every trip they hold, by token alone', function () {
    $user = User::factory()->create(['role' => 'joiner']);
    $r = calBooking(['joiner_id' => $user->id]);

    // No session: a calendar server fetches this with no cookies at all.
    $response = $this->get(route('calendar.feed', ['token' => $user->calendarToken()]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    expect($response->getContent())->toContain('Mount Pulag Traverse')
        ->and($response->getContent())->toContain('UID:booking-'.$r->public_token.'@terra');
});

it('gives nothing to a token that is not a real one', function () {
    $this->get(route('calendar.feed', ['token' => 'not-a-real-token']))->assertNotFound();
});

it('never leaks one joiner trips into another feed', function () {
    $mine = User::factory()->create(['role' => 'joiner']);
    $theirs = User::factory()->create(['role' => 'joiner']);
    calBooking(['joiner_id' => $theirs->id], ['title' => 'Not Yours']);

    $body = $this->get(route('calendar.feed', ['token' => $mine->calendarToken()]))->getContent();

    expect($body)->not->toContain('Not Yours');
});

it('hands a guest their single booking without an account', function () {
    // A guest booking has no user, so the per-booking file is the only route they have.
    $r = calBooking(['joiner_id' => null]);

    $response = $this->get(route('calendar.booking', ['token' => $r->public_token]));

    $response->assertOk();
    expect($response->getContent())->toContain('Mount Pulag Traverse');
});

it('mints a calendar token once and keeps it', function () {
    $user = User::factory()->create(['role' => 'joiner']);

    expect($user->calendar_token)->toBeNull();

    $first = $user->calendarToken();

    // Stable, or every render would invalidate the subscription somebody just set up.
    expect($first)->not->toBeEmpty()
        ->and($user->fresh()->calendarToken())->toBe($first);
});

it('does not mint a secret for a joiner who never asks for one', function () {
    // Minting writes a row. Most joiners never subscribe, and paying for a secret they never use
    // on every page load is the cost this guards against.
    $user = User::factory()->create(['role' => 'joiner']);

    Livewire\Livewire::actingAs($user)->test(Dashboard::class);

    expect($user->fresh()->calendar_token)->toBeNull();
});

it('mints and shows the address when the joiner asks', function () {
    $user = User::factory()->create(['role' => 'joiner']);

    $page = Livewire\Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->call('revealCalendar');

    $token = $user->fresh()->calendar_token;

    expect($token)->not->toBeNull()
        ->and($page->get('calendarUrl'))->toContain($token);
});
