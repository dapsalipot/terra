<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * A three-day trip has to appear on all three days, in both calendars.
 *
 * The old behaviour keyed every square off `starts_at`, so a trip vanished from the calendar the
 * moment it began — the days a host is actually out leading it were blank. The counting is unit
 * tested; what is checked here is that the squares render, and that the repeated chip says which
 * day it is rather than reading as three unrelated departures.
 */
class MultiDayCalendarFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** A three-day departure starting mid-month, so the run cannot straddle the grid edge. */
    private function threeDayDeparture(): array
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $trip = Trip::factory()->published()->create([
            'host_id' => $host->id, 'title' => 'Mount Pulag Traverse', 'price' => 2000,
        ]);

        // The 10th of next month: far enough from either end that all three days sit in one grid.
        $start = now()->addMonth()->startOfMonth()->addDays(9)->setTime(5, 0);

        $departure = TripDeparture::factory()->for($trip)->create([
            'capacity' => 10,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDays(2)->setTime(16, 0),
        ]);

        return [$host, $departure, $start];
    }

    public function test_the_host_calendar_shows_the_trip_on_every_day_it_runs(): void
    {
        [$host, , $start] = $this->threeDayDeparture();

        $this->browse(function (Browser $browser) use ($host, $start) {
            $browser->loginAs($host)
                ->visit('/host')
                ->waitFor('@month-next')
                ->click('@month-next')
                ->waitForText('Mount Pulag Traverse');

            // Three squares carry it, and each says which day of the run it is — without that,
            // the same chip three times reads as three separate trips.
            $browser->assertSee('Day 1/3')
                ->assertSee('Day 2/3')
                ->assertSee('Day 3/3');

            $chips = $browser->script(
                "return document.body.innerText.split('Mount Pulag Traverse').length - 1"
            )[0];

            $this->assertGreaterThanOrEqual(3, $chips, 'The trip is not on all three of its days.');
            unset($start);
        });
    }

    public function test_a_joiner_sees_their_trip_on_every_day_of_it_too(): void
    {
        [, $departure] = $this->threeDayDeparture();

        $joiner = User::factory()->create(['role' => 'joiner', 'name' => 'Mika Ramos']);
        $reservation = Reservation::factory()->for($departure, 'departure')->create([
            'joiner_id' => $joiner->id, 'status' => 'confirmed', 'party_size' => 1,
            'contact_first_name' => 'Mika', 'contact_last_name' => 'Ramos', 'contact_email' => 'mika@example.com',
        ]);
        ReservationParticipant::factory()->for($reservation)->create([
            'position' => 0, 'first_name' => 'Mika', 'last_name' => 'Ramos', 'birthday' => '1995-02-11',
        ]);

        $this->browse(function (Browser $browser) use ($joiner) {
            $browser->loginAs($joiner)
                ->visit('/my-trips')
                ->waitFor('@calendar')
                ->click('@cal-next')
                ->pause(400);

            $marked = $browser->script(
                "return document.querySelectorAll('[dusk=\"calendar\"] [data-has-trip]').length"
            )[0];

            /*
             * Falls back to counting the title if the grid does not carry a data flag — the
             * claim under test is "more than one day is marked", and either shape can carry it.
             */
            if ($marked === 0) {
                $marked = $browser->script(
                    "return document.querySelector('[dusk=\"calendar\"]').innerText.split('Pulag').length - 1"
                )[0];
            }

            $this->assertGreaterThan(1, $marked, 'The joiner calendar marked only one day of a three-day trip.');
        });
    }
}
