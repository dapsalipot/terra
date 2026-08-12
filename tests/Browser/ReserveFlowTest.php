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
                ->waitForText('Conquering trails')
                // "Departing soon" is scroll-revealed (opacity 0 until in view), so scroll like a user would
                ->scrollIntoView('@departing-soon')
                ->waitForText('Departing soon');
        });
    }

    public function test_a_guest_clicking_join_reaches_registration_without_logging_in(): void
    {
        $departure = $this->publishedDeparture();

        $this->browse(function (Browser $browser) use ($departure) {
            $browser->visit('/t/'.$departure->trip->slug)
                ->assertSee('Mount Rinjani Traverse')
                ->assertSee('spots')
                ->press('Join this trip')
                ->waitForText('Where the host reaches you about this booking')
                ->assertPathIs('/t/'.$departure->trip->slug.'/join');
        });
    }

    public function test_a_joiner_reserves_pays_and_host_confirms(): void
    {
        $departure = $this->publishedDeparture();
        $joiner = User::factory()->create(['role' => 'joiner', 'name' => 'Mika Ramos']);

        $this->browse(function (Browser $browser) use ($departure, $joiner) {
            // The joiner asks for a seat. Under the new order that is all this step does —
            // there is nothing to pay yet, because there may not be a seat yet.
            $browser->loginAs($joiner)
                ->visit('/t/'.$departure->trip->slug)
                ->press('Join this trip')
                // Pre-fill splits the legacy `name`, but a mobile number and a birthday are
                // still required — the account has neither.
                ->waitForText('Your details')
                ->type('@contact-phone', '09171234567')
                ->keys('@seat-0-birthday', '05', '14', '1996')
                ->type('@seat-0-ec-name', 'Rosa Reyes')
                ->type('@seat-0-ec-phone', '09171112222')
                ->click('@submit-registration')
                // the primary button opens the review sheet; the request is sent inside it
                ->waitFor('@confirm-booking')
                ->click('@confirm-booking')
                ->waitForText('is reviewing your request')
                // No checkout on an ungranted seat. This is the guarantee the reorder buys.
                ->assertMissing('@submit-proof');
        });

        $reservation = $departure->reservations()->firstOrFail();

        // The host grants the seat.
        $this->browse(function (Browser $browser) use ($departure, $reservation) {
            $browser->loginAs($departure->trip->host)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->assertSee('Mika Ramos')
                ->click('@open-'.$reservation->id)
                ->waitFor('@confirm-'.$reservation->id)
                ->click('@confirm-'.$reservation->id)
                ->waitFor('@confirm-'.$reservation->id.'-yes')
                ->click('@confirm-'.$reservation->id.'-yes')
                ->waitForText('Confirmed');
        });

        $this->assertSame('confirmed', $reservation->fresh()->status);

        // Now, and only now, the joiner can pay.
        $this->browse(function (Browser $browser) use ($joiner, $reservation) {
            $browser->loginAs($joiner)
                ->visit('/r/'.$reservation->public_token)
                ->waitForText("You're confirmed")
                ->assertSee('•••• 4821')                       // host payment method shows
                ->type('reference_no', '9021 5567 3382')
                ->click('@submit-proof')
                // The status stays `confirmed` — sending money does not un-accept the seat — so
                // the receipt bar, not the heading, is what says the proof landed.
                ->waitFor('@proof-sent')
                ->assertSeeIn('@proof-sent', 'waiting on Ridgeline');
        });

        // And the host accepts the money as a separate decision from the seat.
        $this->browse(function (Browser $browser) use ($departure, $reservation) {
            $browser->loginAs($departure->trip->host)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->click('@open-'.$reservation->id)
                ->waitFor('@verify-'.$reservation->id)
                ->click('@verify-'.$reservation->id)
                ->waitFor('@pay-form')
                ->click('@pay-confirm')
                ->waitForText('Paid in full');
        });

        $this->assertSame('confirmed', $reservation->fresh()->status);
    }
}
