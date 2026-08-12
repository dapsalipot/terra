<?php

use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

it('links a host who has a handle', function () {
    $host = User::factory()->create(['role' => 'host', 'name' => 'Ridgeline', 'handle' => 'ridgeline']);

    $html = Blade::render('<x-host-link :host="$host" />', ['host' => $host]);

    expect($html)->toContain(route('hosts.show', $host))
        ->and($html)->toContain('Ridgeline')
        ->and($html)->toContain('<a');
});

it('renders plain text rather than a link to nowhere when there is no handle', function () {
    $host = User::factory()->create(['role' => 'host', 'name' => 'Nomad', 'handle' => null]);

    $html = Blade::render('<x-host-link :host="$host" />', ['host' => $host]);

    expect($html)->toContain('Nomad')
        ->and($html)->not->toContain('<a');
});

it('falls back to initials when the host has no avatar', function () {
    $host = User::factory()->create([
        'role' => 'host', 'name' => 'Ada Reyes', 'handle' => 'ada', 'avatar' => null,
    ]);

    $html = Blade::render('<x-host-link :host="$host" />', ['host' => $host]);

    expect($html)->toContain('AR')->and($html)->not->toContain('<img');
});

it('can drop the avatar or the name', function () {
    $host = User::factory()->create(['role' => 'host', 'name' => 'Ridgeline', 'handle' => 'ridgeline']);

    $nameOnly = Blade::render('<x-host-link :host="$host" :avatar="false" />', ['host' => $host]);
    $avatarOnly = Blade::render('<x-host-link :host="$host" :name="false" />', ['host' => $host]);

    expect($nameOnly)->toContain('Ridgeline')
        // strip_tags, because an avatar-only link still names the host in its title attribute —
        // that is the only label a screen reader would get, so it must stay.
        ->and(strip_tags($avatarOnly))->not->toContain('Ridgeline')
        ->and($avatarOnly)->toContain('title="View Ridgeline');
});

it('sits above the card overlay so it stays clickable inside a stretched-link card', function () {
    // Nested anchors are invalid HTML, so the trip card is a container with an absolutely
    // positioned link. The host chip only survives that if it is raised out of the overlay.
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline']);

    $html = Blade::render('<x-host-link :host="$host" />', ['host' => $host]);

    expect($html)->toContain('relative')->and($html)->toContain('z-10');
});

it('does not nest an anchor inside the trip card link', function () {
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id]);
    $trip->load('openDepartures');

    $html = Blade::render('<x-trip-card :trip="$trip" />', ['trip' => $trip]);

    // The card's own link must close before the host link opens.
    $cardLinkOpens = strpos($html, route('trips.show', $trip));
    $hostLinkOpens = strpos($html, route('hosts.show', $host));

    expect($cardLinkOpens)->not->toBeFalse()
        ->and($hostLinkOpens)->not->toBeFalse()
        // Anything between the two must not leave the card anchor open.
        ->and(substr_count(substr($html, $cardLinkOpens, $hostLinkOpens - $cardLinkOpens), '</a>'))
        ->toBeGreaterThan(0);
});
