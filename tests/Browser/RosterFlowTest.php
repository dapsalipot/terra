<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\PaymentSubmission;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * The roster — the page a host actually works from on the morning of a trip.
 *
 * It went through several redesigns and the recurring complaints were all things only a browser
 * can see: statuses styled every which way, details that stacked in one row and sat side by side
 * in the next, proof hidden behind a button that showed one image. So the assertions here lean on
 * rendered geometry and on the modal opening, not just on the right rows coming back.
 */
class RosterFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** A departure with a paid booking, an unpaid one, and a proof waiting to be looked at. */
    private function loadedDeparture(): array
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $trip = Trip::factory()->published()->create([
            'host_id' => $host->id, 'title' => 'Mount Pulag Traverse', 'price' => 2000,
        ]);
        $departure = TripDeparture::factory()->for($trip)->create([
            'capacity' => 20, 'starts_at' => now()->addWeek(),
        ]);

        // Booked by one person, travelling as another. The roster is a list of who is coming,
        // so the traveller's name is what has to be on the row — this pair is what caught the
        // earlier version showing the booker instead.
        $booked = Reservation::factory()->for($departure, 'departure')->create([
            'joiner_id' => null, 'status' => 'payment_submitted', 'party_size' => 1,
            'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes', 'contact_email' => 'ada@example.com',
        ]);
        ReservationParticipant::factory()->for($booked)->create([
            'position' => 0, 'first_name' => 'Tala', 'last_name' => 'Cruz', 'birthday' => '2001-07-19',
        ]);
        PaymentSubmission::factory()->for($booked)->create([
            'reference_no' => 'GC-88213', 'method' => 'GCash', 'submitted_at' => now(),
        ]);

        $other = Reservation::factory()->for($departure, 'departure')->create([
            'joiner_id' => null, 'status' => 'awaiting_payment', 'party_size' => 1,
            'contact_first_name' => 'Miguel', 'contact_last_name' => 'Santos', 'contact_email' => 'mig@example.com',
        ]);
        ReservationParticipant::factory()->for($other)->create([
            'position' => 0, 'first_name' => 'Miguel', 'last_name' => 'Santos', 'birthday' => '1990-01-05',
        ]);

        return [$host, $departure, $booked, $other];
    }

    public function test_the_roster_names_the_traveller_not_whoever_paid(): void
    {
        [$host, $departure] = $this->loadedDeparture();

        $this->browse(function (Browser $browser) use ($host, $departure) {
            $browser->loginAs($host)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->waitForText('Tala Cruz')
                // The booker is still shown, but as an attribution on the traveller's row rather
                // than in place of them.
                ->assertSee('Booked by');
        });
    }

    public function test_every_row_lines_up_instead_of_stacking_in_some_rows_and_not_others(): void
    {
        [$host, $departure] = $this->loadedDeparture();

        $this->browse(function (Browser $browser) use ($host, $departure) {
            $browser->loginAs($host)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->waitForText('Tala Cruz');

            /*
             * Two travellers, two rows. If one row's name cell starts at a different x than the
             * other's, the columns are not columns — that is the "sometimes stacked, sometimes
             * side by side" complaint, and it is invisible to any assertion on text alone.
             */
            // Rows are list items now, not table rows: the redesign dropped the table because a
            // cell that had to hold six controls in one row and one in the next is what made the
            // columns stop being columns.
            $lefts = $browser->script(
                "return Array.from(document.querySelectorAll('li[dusk^=\"reservation-\"] > div'))
                 .map(c => Math.round(c.getBoundingClientRect().left))"
            )[0];

            $this->assertGreaterThanOrEqual(2, count($lefts), 'Expected a row per booking.');
            $this->assertCount(1, array_unique($lefts), 'Roster rows do not share a left edge.');
        });
    }

    public function test_a_waiting_proof_is_flagged_and_opens_as_a_list_in_a_modal(): void
    {
        [$host, $departure, $booked] = $this->loadedDeparture();

        $this->browse(function (Browser $browser) use ($host, $departure, $booked) {
            $browser->loginAs($host)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->waitFor('@new-proof-'.$booked->id)
                // Actions live in the drawer now, so the booking has to be opened first.
                ->click('@open-'.$booked->id)
                ->waitFor('@roster-drawer')
                ->click('@modal-proof-'.$booked->id)
                ->waitFor('@proof-modal')
                // The reference number is the part a host reconciles against their bank app, so
                // it has to be readable in the modal, not only the image.
                ->assertSeeIn('@proof-modal', 'GC-88213')
                ->click('@close-proofs')
                ->waitUntilMissing('@proof-modal');
        });
    }

    public function test_filtering_to_unpaid_hides_the_bookings_that_are_settled(): void
    {
        [$host, $departure, $booked] = $this->loadedDeparture();

        // Settle one of them so the filter has something to remove.
        app(ReservationService::class)->markFullyPaid($booked, $host);

        $this->browse(function (Browser $browser) use ($host, $departure) {
            $browser->loginAs($host)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->waitForText('Tala Cruz')
                ->click('@filter-unpaid')
                ->waitUntilMissingText('Tala Cruz')
                ->assertSee('Miguel Santos');
        });
    }

    public function test_searching_the_roster_finds_a_traveller_by_name(): void
    {
        [$host, $departure] = $this->loadedDeparture();

        $this->browse(function (Browser $browser) use ($host, $departure) {
            $browser->loginAs($host)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->waitForText('Miguel Santos')
                ->type('@roster-search', 'Tala')
                ->waitUntilMissingText('Miguel Santos')
                ->assertSee('Tala Cruz');
        });
    }

    public function test_confirming_a_seat_nobody_paid_for_does_not_mark_it_paid(): void
    {
        // Miguel sent no proof at all. That is the case the bug applied to: accepting a seat
        // used to fabricate a payment record, and on a trip with no deposit `payable_now` is the
        // full price, so the booking jumped straight to "Paid in full" and the joiner was locked
        // out of ever paying. A booking that *does* carry proof is different — confirming it is
        // genuinely the host verifying that proof, and it is meant to credit the money.
        [$host, $departure, , $unpaid] = $this->loadedDeparture();

        $this->browse(function (Browser $browser) use ($host, $departure, $unpaid) {
            $browser->loginAs($host)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->click('@open-'.$unpaid->id)
                ->waitFor('@confirm-'.$unpaid->id)
                ->click('@confirm-'.$unpaid->id)
                ->waitForText('uploaded no proof')
                ->click('@confirm-'.$unpaid->id.'-yes')
                ->waitUntilMissing('@confirm-'.$unpaid->id);

            $browser->assertMissing('@paid-chip-'.$unpaid->id)
                ->assertVisible('@mark-paid-'.$unpaid->id);
        });
    }
}
