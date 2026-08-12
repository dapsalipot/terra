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

class ChatFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_a_confirmed_joiner_can_post_to_the_trip_chat(): void
    {
        $host = User::factory()->create(['role' => 'host', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create();
        $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Mount Rinjani Traverse']);
        $departure = TripDeparture::factory()->for($trip)->create();
        $joiner = User::factory()->create(['role' => 'joiner', 'name' => 'Mika Ramos']);
        Reservation::factory()->confirmed()->create(['trip_departure_id' => $departure->id, 'joiner_id' => $joiner->id]);

        $this->browse(function (Browser $browser) use ($joiner, $departure) {
            $browser->loginAs($joiner)
                ->visit('/chat/'.$departure->id)
                ->assertSee('Mount Rinjani Traverse')
                ->type('@chat-input', 'What time is the meetup?')
                ->click('@chat-send')
                ->waitForText('What time is the meetup?')
                ->assertSeeIn('@messages', 'What time is the meetup?');
        });
    }

    public function test_a_non_member_is_blocked(): void
    {
        $trip = Trip::factory()->published()->create();
        $departure = TripDeparture::factory()->for($trip)->create();
        $stranger = User::factory()->create(['role' => 'joiner']);

        $this->browse(function (Browser $browser) use ($stranger, $departure) {
            $browser->loginAs($stranger)
                ->visit('/chat/'.$departure->id)
                ->assertSee('403');
        });
    }
}
