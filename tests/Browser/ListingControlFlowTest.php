<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ListingControlFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_a_host_can_take_an_offering_off_the_listings(): void
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco', 'name' => 'Trail Collective']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $trip = Trip::factory()->published()->create([
            'host_id' => $host->id,
            'title' => 'Sagada Echo Valley Hike',
            'place_id' => Place::factory()->create(['name' => 'Sagada', 'country' => 'Philippines'])->id,
        ]);
        TripDeparture::factory()->for($trip)->create(['starts_at' => now()->addWeeks(3)]);
        $menu = '@visibility-solo-'.$trip->id;
        $chip = '@chip-'.$trip->id;

        $this->browse(function (Browser $browser) use ($host, $trip, $menu, $chip) {
            $browser->loginAs($host)
                // listed to everyone to begin with, which is what gives its absence below any meaning
                ->visit('/trips')
                ->assertSee($trip->title)
                ->visit('/host/trips')
                ->waitFor($menu)
                ->select($menu, 'link')
                // "Link only" is also one of the menu's own options, so waiting for those words proves nothing:
                // the chip is drawn from the saved trip, so it is only there once the change has landed
                ->waitFor($chip)
                ->assertSeeIn($chip, 'Link only')
                // a menu of actions goes back to its prompt instead of claiming to be the offering's state
                ->assertSelected($menu, '')
                ->logout()
                ->visit('/trips')
                ->assertDontSee($trip->title)
                // off the listings is not off the internet: the link still opens it
                ->visit('/t/'.$trip->slug)
                ->assertSee($trip->title);
        });
    }
}
