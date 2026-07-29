<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ReserveFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    private function publishedDeparture(): TripDeparture
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create([
            'currency' => 'PHP',
            'payment_methods' => [['label' => 'Bank transfer', 'detail' => '•••• 4821']],
        ]);
        $trip = Trip::factory()->published()->create([
            'host_id' => $host->id, 'title' => 'Mount Rinjani Traverse', 'price' => 180,
        ]);

        return TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);
    }

    public function test_home_hero_renders(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->waitForText('Better trips with')
                // "Departing soon" is scroll-revealed (opacity 0 until in view), so scroll like a user would
                ->scrollIntoView('@departing-soon')
                ->waitForText('Departing soon');
        });
    }

    public function test_a_guest_clicking_reserve_is_sent_to_login(): void
    {
        $departure = $this->publishedDeparture();

        $this->browse(function (Browser $browser) use ($departure) {
            $browser->visit('/t/'.$departure->trip->slug)
                ->assertSee('Mount Rinjani Traverse')
                ->assertSee('spots')
                ->press('Reserve')
                ->waitForLocation('/login')
                ->assertPathIs('/login');
        });
    }

    public function test_a_joiner_reserves_pays_and_host_confirms(): void
    {
        $departure = $this->publishedDeparture();
        $joiner = User::factory()->create(['role' => 'joiner', 'name' => 'Mika Ramos']);

        $this->browse(function (Browser $browser) use ($departure, $joiner) {
            // joiner reserves
            $browser->loginAs($joiner)
                ->visit('/t/'.$departure->trip->slug)
                ->press('Reserve')
                ->waitForText('Pay your host')
                ->assertSee('•••• 4821')                       // host payment method shows
                ->type('reference_no', '9021 5567 3382')
                ->press('Submit proof of payment')
                ->waitForText('Waiting for Ridgeline');        // pending host confirmation
        });

        // host confirms via roster
        $reservation = $departure->reservations()->firstOrFail();
        $this->browse(function (Browser $browser) use ($departure, $reservation) {
            $browser->loginAs($departure->trip->host)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->assertSee('Mika Ramos')
                // hook, not label: "Confirm" became "Mark as paid" and will change again
                ->click('@confirm-'.$reservation->id)
                ->waitForText('Confirmed');
        });

        $this->assertSame('confirmed', $reservation->fresh()->status);
    }
}
