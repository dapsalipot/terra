<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * The moderation console, and the door in front of it.
 *
 * Moderation is the one area where a broken guard is worse than a broken feature, so the
 * boundary is exercised in a real session — cookies, middleware stack and all — rather than
 * only through Livewire's test harness, which never runs the HTTP kernel the same way.
 *
 * Every destructive control here sits behind <x-confirm-action>, so each of these tests goes
 * through the dialog rather than calling the Livewire method. That is the point: a moderator
 * who cannot reach the confirm button has no working moderation, however sound the service is.
 */
class AdminConsoleFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** Handles are unique, so each host gets its own — two calls in one test must not collide. */
    private function publishedTrip(string $title, string $handle, string $hostName = 'Ridgeline'): Trip
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => $handle, 'name' => $hostName]);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => $title, 'price' => 2000]);
        TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

        return $trip;
    }

    public function test_a_joiner_is_told_the_admin_area_does_not_exist_rather_than_that_they_are_not_allowed_in(): void
    {
        $joiner = User::factory()->create(['role' => 'joiner']);

        $this->browse(function (Browser $browser) use ($joiner) {
            // 404, not 403. A 403 confirms there is something there to find.
            $browser->loginAs($joiner)
                ->visit('/admin')
                ->assertDontSee('Admin')
                ->assertDontSee('Suspend');
        });
    }

    public function test_a_host_gets_no_further_than_a_joiner_does(): void
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'someone']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

        $this->browse(function (Browser $browser) use ($host) {
            $browser->loginAs($host)
                ->visit('/admin')
                ->assertDontSee('Suspend');
        });
    }

    public function test_an_admin_unpublishes_a_trip_and_the_public_catalogue_drops_it(): void
    {
        $trip = $this->publishedTrip('Mount Pulag Traverse', 'ridgeline');
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Moderator']);

        $this->browse(function (Browser $browser) use ($admin, $trip) {
            // It is on the public catalogue to begin with — otherwise the assertion after the
            // unpublish proves nothing at all.
            $browser->visit('/trips')
                ->waitForText('Mount Pulag Traverse');

            $browser->loginAs($admin)
                ->visit('/admin')
                ->waitForText('Mount Pulag Traverse')
                ->click('@unpublish-'.$trip->id)
                ->waitForText('Take this trip out of discovery?')
                ->click('@unpublish-'.$trip->id.'-yes')
                ->waitForText('Republish');

            $browser->visit('/trips')
                ->pause(400)
                ->assertDontSee('Mount Pulag Traverse');

            // And the trip's own page is gone too, not merely hidden from the index — a link
            // already shared in a group chat is the likelier route in than the catalogue.
            $browser->visit('/t/'.$trip->slug)
                ->assertDontSee('Join this trip');
        });
    }

    public function test_searching_the_console_narrows_to_the_trip_being_looked_into(): void
    {
        $this->publishedTrip('Mount Pulag Traverse', 'ridgeline');
        $this->publishedTrip('Batulao Loop', 'coastal', 'Coastal Wanderers');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin')
                ->waitForText('Batulao Loop')
                ->type('@admin-search', 'Pulag')
                ->waitUntilMissingText('Batulao Loop')
                ->assertSee('Mount Pulag Traverse');
        });
    }

    public function test_a_suspended_host_loses_the_console_while_their_joiners_keep_their_bookings(): void
    {
        $trip = $this->publishedTrip('Mount Pulag Traverse', 'ridgeline');
        $host = $trip->host;
        $admin = User::factory()->create(['role' => 'admin']);

        $this->browse(function (Browser $browser) use ($admin, $host) {
            $browser->loginAs($admin)
                ->visit('/admin')
                ->waitForText('Admin')
                ->click('@tab-hosts')
                ->waitForText('Ridgeline')
                ->click('@suspend-'.$host->id)
                ->waitForText('Suspend Ridgeline?')
                ->click('@suspend-'.$host->id.'-yes')
                ->waitForText('Lift suspension');

            // Suspension stops them selling. It is deliberately not a deletion, so the trip
            // page still exists for the people already booked onto it.
            $browser->loginAs($host)
                ->visit('/host/trips/create')
                ->assertDontSee('Publish trip');
        });
    }
}
