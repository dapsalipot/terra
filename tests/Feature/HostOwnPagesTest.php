<?php

use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Support\Str;

/*
 * A host's own pages are theirs: their page, one of their trips, its join page and a booking made on it. Whatever
 * a host controls there stays about them, so someone on the way to booking is never handed to another host.
 *
 * The nav is the site's, not the host's, and it keeps its Explore link on every page: nobody's exploring is
 * restricted. This is a guard on what the pages contain, so it locks today's behaviour rather than driving new code.
 */

/** A host with a page of their own and a published trip with a departure still open to join. */
function ownPagesHost(string $name, string $tripTitle): array
{
    $host = User::factory()->create(['role' => 'host', 'name' => $name, 'handle' => Str::slug($name)]);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return ['host' => $host, 'trip' => ownPagesTrip($host, $tripTitle)];
}

/** A published trip of `$host` that anyone can find, with one departure still open to join. */
function ownPagesTrip(User $host, string $title): Trip
{
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => $title]);
    TripDeparture::factory()->for($trip)->create(['starts_at' => now()->addDays(10)]);

    return $trip;
}

/** The top nav as the page prints it: the footer and a trip page's back link point at Explore too, so they cannot vouch for it. */
function ownPagesNav(string $html): string
{
    return preg_match('#<nav class="site-nav.*?</nav>#s', $html, $found) ? $found[0] : '';
}

it('never shows another host on a host\'s own pages, and never takes Explore away', function () {
    $mine = ownPagesHost('Ridgeline', 'Pulag Sea of Clouds');
    ownPagesTrip($mine['host'], 'Ambangeg Ridge Walk');   // a second trip of theirs, so their trip page has a "More from" row
    $other = ownPagesHost('Summit Co', 'Rinjani Crater Rim');
    $booking = Reservation::factory()->for($mine['trip']->departure, 'departure')->create([
        'joiner_id' => null, 'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes',
    ]);

    // Each page, with what must show on it (the host's name besides): a blank page would pass every absence below.
    $pages = [
        'host page' => [route('hosts.handle', $mine['host']), ['Pulag Sea of Clouds', 'Ambangeg Ridge Walk']],
        'trip page' => [route('trips.show', $mine['trip']), ['More from Ridgeline', 'Ambangeg Ridge Walk']],
        'join page' => [route('trips.join', $mine['trip']), ['Pulag Sea of Clouds']],
        'booking page' => [route('reservations.show', ['token' => $booking->public_token]), ['Pulag Sea of Clouds']],
    ];
    $absent = ['Summit Co', 'Rinjani Crater Rim', route('trips.show', $other['trip']), route('hosts.handle', $other['host'])];
    $exploreLink = '#<a href="'.preg_quote(route('trips.index'), '#').'"[^>]*>Explore</a>#';

    // The other host is on the site for anyone to find, or their absence below would prove nothing.
    $this->get(route('trips.index'))->assertSee('Rinjani Crater Rim');

    // Collected rather than asserted one by one, so a failure names the page and every page is checked.
    $problems = [];
    foreach ($pages as $label => [$url, $shown]) {
        $response = $this->get($url);
        $html = $response->getContent();

        if ($response->status() !== 200) {
            $problems[] = "The {$label} answers {$response->status()}.";

            continue;
        }
        foreach ([...$shown, 'Ridgeline'] as $text) {
            if (! str_contains($html, $text)) {
                $problems[] = "The {$label} does not show {$text}.";
            }
        }
        foreach ($absent as $leak) {
            if (str_contains($html, $leak)) {
                $problems[] = "The {$label} shows {$leak}.";
            }
        }
        if (! preg_match($exploreLink, ownPagesNav($html))) {
            $problems[] = "The {$label}'s nav has lost its Explore link.";
        }
    }

    expect($problems)->toBe([]);
});
