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

class ReviewFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    private function completedTripFor(User $joiner): Trip
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create();
        $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Mount Rinjani Traverse']);
        $departure = TripDeparture::factory()->completed()->for($trip)->create();
        Reservation::factory()->confirmed()->create(['trip_departure_id' => $departure->id, 'joiner_id' => $joiner->id]);

        return $trip;
    }

    public function test_a_completed_attendee_can_post_a_review(): void
    {
        $joiner = User::factory()->create(['role' => 'joiner', 'name' => 'Mika Ramos']);
        $trip = $this->completedTripFor($joiner);

        $this->browse(function (Browser $browser) use ($joiner, $trip) {
            $browser->loginAs($joiner)
                ->visit('/t/'.$trip->slug)
                ->waitForText('Rate this trip')
                ->click('@rate-trip')
                ->waitFor('@review-body')
                ->type('@review-body', 'Never hiked with strangers before. Left with a group chat.')
                ->click('@post-review')
                ->waitForText('Never hiked with strangers before')
                ->assertSee('Mika Ramos');
        });
    }

    public function test_a_stranger_sees_no_rating_prompt(): void
    {
        $joiner = User::factory()->create(['role' => 'joiner']);
        $trip = Trip::factory()->published()->create(['title' => 'Fuji Sunrise']);

        $this->browse(function (Browser $browser) use ($joiner, $trip) {
            $browser->loginAs($joiner)
                ->visit('/t/'.$trip->slug)
                ->assertSee('Reviews')
                ->assertDontSee('Rate this trip');
        });
    }
}
