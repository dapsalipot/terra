<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * The two ways a stranger arrives at a trip: browsing the host directory, and hitting a
 * departure that has already sold out.
 *
 * Both are live-wire filtered surfaces, which is exactly the kind of thing a feature test
 * cannot vouch for — `->set('search', 'x')` proves the query narrows, not that the debounce
 * fires, the list repaints, and the count under it agrees with what is on screen.
 */
class DiscoveryAndWaitlistFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    private function host(string $name, string $handle, ?string $tagline = null): User
    {
        $host = User::factory()->create(['role' => 'host', 'name' => $name, 'handle' => $handle]);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP', 'tagline' => $tagline]);

        return $host;
    }

    public function test_searching_the_directory_narrows_it_and_clearing_brings_everyone_back(): void
    {
        $this->host('Ridgeline Collective', 'ridgeline', 'Cordillera traverses');
        $this->host('Coastal Wanderers', 'coastal', 'Island hops');

        $this->browse(function (Browser $browser) {
            $browser->visit('/hosts')
                ->waitForText('Ridgeline Collective')
                ->assertSee('Coastal Wanderers')
                ->type('@host-search', 'Ridgeline')
                // .live.debounce.300ms — the repaint is the thing under test, so wait for it
                // rather than asserting into the gap before Livewire has answered.
                ->waitUntilMissingText('Coastal Wanderers')
                ->assertSee('Ridgeline Collective')
                ->assertSeeIn('@host-count', '1')
                ->click('@clear-filters')
                ->waitForText('Coastal Wanderers')
                ->assertSee('Ridgeline Collective');
        });
    }

    public function test_a_search_matching_nobody_says_so_instead_of_showing_an_empty_grid(): void
    {
        $this->host('Ridgeline Collective', 'ridgeline');

        $this->browse(function (Browser $browser) {
            $browser->visit('/hosts')
                ->waitForText('Ridgeline Collective')
                ->type('@host-search', 'zzzznobodyzzzz')
                ->waitFor('@hosts-empty')
                ->assertDontSee('Ridgeline Collective');
        });
    }

    public function test_re_sorting_the_directory_reorders_what_is_on_screen(): void
    {
        // Two hosts whose alphabetical order and follower order disagree, so a sort that does
        // nothing cannot accidentally pass.
        $first = $this->host('Alpha Trails', 'alpha');
        $second = $this->host('Zenith Climbs', 'zenith');
        $first->hostProfile->update(['followers_count' => 2]);
        $second->hostProfile->update(['followers_count' => 400]);

        $this->browse(function (Browser $browser) {
            $browser->visit('/hosts')
                ->waitForText('Alpha Trails')
                ->select('@host-sort', 'followers')
                ->waitForText('Zenith Climbs');

            // Read the rendered order out of the DOM. Asserting "I can see both names" would
            // pass no matter which order they came back in.
            // `href^="/@"` would not match: route() writes absolute URLs, so the attribute
            // starts with the scheme, not the path.
            $names = $browser->script(
                "return Array.from(document.querySelectorAll('a[href*=\"/@\"]'))
                 .map(a => a.textContent.trim()).filter(t => t.includes('Trails') || t.includes('Climbs'))"
            )[0];

            $this->assertNotEmpty($names, 'No host cards were rendered.');
            $this->assertStringContainsString('Zenith', $names[0], 'Most-followed host did not sort first.');
        });
    }

    public function test_a_sold_out_departure_offers_the_waitlist_instead_of_a_booking_form(): void
    {
        $host = $this->host('Ridgeline Collective', 'ridgeline');
        $trip = Trip::factory()->published()->create([
            'host_id' => $host->id, 'title' => 'Mount Pulag Traverse', 'price' => 2000,
        ]);
        $departure = TripDeparture::factory()->for($trip)->create([
            'capacity' => 2, 'starts_at' => now()->addWeek(),
        ]);

        // Fill it. Capacity is measured in seats held, so one booking of two closes a trip of two.
        Reservation::factory()->for($departure, 'departure')->create([
            'joiner_id' => null, 'status' => 'confirmed', 'party_size' => 2,
            'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes', 'contact_email' => 'ada@example.com',
        ]);

        $this->browse(function (Browser $browser) use ($trip) {
            $browser->visit('/t/'.$trip->slug.'/join')
                ->waitForText('This departure is full')
                ->type('@waitlist-name', 'Mika Ramos')
                ->type('@waitlist-email', 'mika@example.com')
                ->click('@join-waitlist')
                ->waitFor('@waitlisted')
                // The form must go away. Leaving it on screen next to the confirmation is how
                // somebody signs up three times and then gets three emails when a seat opens.
                ->assertMissing('@join-waitlist');
        });
    }
}
