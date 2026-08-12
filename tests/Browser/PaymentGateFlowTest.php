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
 * Payment follows the seat, and the back link goes back.
 *
 * The gate is enforced in the service and asserted there too, but a service that refuses a write
 * is only half the guarantee — the other half is that the page never offers the form in the first
 * place. A button that throws is worse than no button.
 */
class PaymentGateFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    private function booking(array $attrs = [], array $tripAttrs = []): Reservation
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create([
            'currency' => 'PHP',
            'payment_methods' => [['label' => 'GCash', 'detail' => '0917 000 1111']],
        ]);
        $trip = Trip::factory()->published()->create(array_merge([
            'host_id' => $host->id, 'title' => 'Mount Pulag Traverse', 'price' => 2000,
        ], $tripAttrs));
        $departure = TripDeparture::factory()->for($trip)->create([
            'capacity' => 10, 'starts_at' => now()->addMonth(),
        ]);

        $reservation = Reservation::factory()->for($departure, 'departure')->create(array_merge([
            'joiner_id' => null, 'status' => 'pending', 'party_size' => 1,
            'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes',
            'contact_email' => 'ada@example.com', 'contact_phone' => '09171234567',
        ], $attrs));
        ReservationParticipant::factory()->for($reservation)->create([
            'position' => 0, 'is_booker' => true, 'first_name' => 'Ada', 'last_name' => 'Reyes',
            'birthday' => '1998-04-02',
        ]);

        return $reservation;
    }

    public function test_a_seat_the_host_has_not_granted_offers_no_way_to_pay(): void
    {
        $reservation = $this->booking();

        $this->browse(function (Browser $browser) use ($reservation) {
            $browser->visit('/r/'.$reservation->public_token)
                ->waitForText('is reviewing your request')
                // No form, and no thumb-reach pay bar on the phone layout either.
                ->assertMissing('@submit-proof')
                ->assertMissing('@open-pay')
                // And it does not lead with an instruction to pay something.
                ->assertDontSee('Pay your host');
        });
    }

    public function test_granting_the_seat_is_what_reveals_the_checkout(): void
    {
        $reservation = $this->booking(['status' => 'confirmed', 'confirmed_at' => now()]);

        $this->browse(function (Browser $browser) use ($reservation) {
            $browser->visit('/r/'.$reservation->public_token)
                ->waitFor('@submit-proof')
                ->assertSee('0917 000 1111');
        });
    }

    public function test_a_confirmed_booking_says_when_the_money_is_due(): void
    {
        $reservation = $this->booking([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'full_payment_due_at' => now()->addDays(5)->setTime(17, 0),
        ]);

        $this->browse(function (Browser $browser) use ($reservation) {
            $browser->visit('/r/'.$reservation->public_token)
                ->waitForText('Pay by')
                ->assertSee(now()->addDays(5)->format('j M'));
        });
    }

    public function test_a_late_booking_says_so_without_losing_its_seat(): void
    {
        $reservation = $this->booking([
            'status' => 'confirmed',
            'confirmed_at' => now()->subWeek(),
            'full_payment_due_at' => now()->subDays(2),
        ]);

        $this->browse(function (Browser $browser) use ($reservation) {
            $browser->visit('/r/'.$reservation->public_token)
                ->waitForText('Payment was due')
                // Still theirs, and still payable. Nothing cancels on a timer.
                ->assertSee("You're confirmed")
                ->assertVisible('@submit-proof');
        });

        $this->browse(function (Browser $browser) use ($reservation) {
            $browser->loginAs($reservation->departure->trip->host)
                ->visit('/host/departures/'.$reservation->trip_departure_id.'/roster')
                ->waitFor('@overdue-'.$reservation->id)
                ->assertSeeIn('@overdue-'.$reservation->id, 'overdue');
        });
    }

    public function test_back_returns_to_wherever_you_came_from(): void
    {
        $reservation = $this->booking(['status' => 'confirmed', 'confirmed_at' => now()]);
        $trip = $reservation->departure->trip;

        $this->browse(function (Browser $browser) use ($reservation, $trip) {
            /*
             * Arrive at the booking from the hosts directory rather than from the trip page.
             * The old link always went to the trip, so somebody who came from their dashboard was
             * sent somewhere they had never been — which is not what a back arrow means.
             */
            $browser->visit('/hosts')
                ->waitForText('Ridgeline')
                ->visit('/r/'.$reservation->public_token)
                ->waitForText('Back')
                ->click('@back')
                ->pause(700)
                ->assertPathIs('/hosts');

            unset($trip);
        });
    }

    public function test_back_still_works_for_someone_arriving_cold_from_a_link(): void
    {
        $reservation = $this->booking(['status' => 'confirmed', 'confirmed_at' => now()]);
        $trip = $reservation->departure->trip;

        $this->browse(function (Browser $browser) use ($reservation, $trip) {
            // A fresh tab opened from an email has no history to go back through, so the
            // href fallback has to be a real destination rather than a dead control.
            $browser->visit('/r/'.$reservation->public_token)
                ->waitForText('Back')
                ->assertAttributeContains('@back', 'href', '/t/'.$trip->slug);
        });
    }
}
