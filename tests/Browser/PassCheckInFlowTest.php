<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * The pass and the scanner, driven the way the two people at a trailhead actually drive them.
 *
 * Everything here was previously covered only at the service layer, where a pass code is a
 * string on a model. The parts that can fail in a browser — the QR image actually loading, the
 * code being readable off a phone screen, the host typing it in on a signal-less mountain road —
 * had no coverage at all.
 */
class PassCheckInFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** A paid booking with its pass already issued, plus the host who issued it. */
    private function paidBooking(): array
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $trip = Trip::factory()->published()->create([
            'host_id' => $host->id, 'title' => 'Mount Pulag Traverse', 'price' => 2000,
        ]);
        $departure = TripDeparture::factory()->for($trip)->create([
            'capacity' => 10, 'starts_at' => now()->addWeek(),
        ]);

        $joiner = User::factory()->create(['role' => 'joiner', 'name' => 'Ada Reyes']);
        $reservation = Reservation::factory()->for($departure, 'departure')->create([
            'joiner_id' => $joiner->id, 'status' => 'confirmed', 'party_size' => 1,
            'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes', 'contact_email' => 'ada@example.com',
        ]);
        ReservationParticipant::factory()->for($reservation)->create([
            'position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02',
        ]);

        // The host tagging the money as in is what mints the pass — nothing else does.
        app(ReservationService::class)->markFullyPaid($reservation, $host);

        return [$host, $joiner, $reservation->fresh(), $departure];
    }

    public function test_the_pass_is_on_the_booking_itself_not_behind_a_link(): void
    {
        [, $joiner, $reservation] = $this->paidBooking();
        $code = $reservation->participants()->first()->pass_code;

        $this->browse(function (Browser $browser) use ($joiner, $reservation, $code) {
            $browser->loginAs($joiner)
                ->visit('/r/'.$reservation->public_token)
                ->waitFor('@pass-section')
                /*
                 * The code, on the page you land on. It used to be a banner announcing that a pass
                 * existed somewhere else — a hop for the one thing a joiner opens under time
                 * pressure, at a trailhead, with a host waiting.
                 */
                ->assertSee($code)
                ->assertSee('Ada Reyes');

            /*
             * A broken <img> still renders as an element, so asserting presence proves nothing.
             * naturalWidth is the only thing that says bytes arrived and decoded as an image.
             */
            $decoded = $browser->script(
                "return (() => { const i = document.querySelector('[dusk=pass-section] img[src*=\"/qr\"]');
                 return i ? i.naturalWidth : 0; })()"
            )[0];

            $this->assertGreaterThan(0, $decoded, 'The pass QR did not decode as an image.');
        });
    }

    public function test_the_printable_page_still_exists_for_the_people_who_saved_it(): void
    {
        [, $joiner, $reservation] = $this->paidBooking();
        $code = $reservation->participants()->first()->pass_code;

        $this->browse(function (Browser $browser) use ($joiner, $reservation, $code) {
            // Inlining the pass must not break a link somebody put on their home screen, and the
            // print route is the one thing a section on a busy page cannot do.
            $browser->loginAs($joiner)
                ->visit('/r/'.$reservation->public_token)
                ->waitFor('@open-pass')
                ->click('@open-pass')
                ->waitFor('@print-pass')
                ->assertSee($code);
        });
    }

    public function test_a_group_gets_a_tab_each_and_one_pass_at_a_time(): void
    {
        [$host, $joiner, $reservation] = $this->paidBooking();

        $second = ReservationParticipant::factory()->for($reservation)->create([
            'position' => 1, 'first_name' => 'Tala', 'last_name' => 'Cruz', 'birthday' => '2001-07-19',
        ]);
        $reservation->update(['party_size' => 2]);
        app(CheckInService::class)->issueFor($reservation->fresh());

        $first = $reservation->participants()->first();

        $this->browse(function (Browser $browser) use ($joiner, $reservation, $first, $second) {
            $browser->loginAs($joiner)
                ->visit('/r/'.$reservation->public_token)
                ->waitFor('@pass-tabs')
                // Ada's pass is showing; Tala's is behind her tab rather than stacked below.
                ->assertSee($first->fresh()->pass_code)
                ->assertDontSee($second->fresh()->pass_code)
                ->click('@pass-tab-'.$second->id)
                ->waitForText($second->fresh()->pass_code)
                // One pass at a time — the fifth person on a party of five should not scroll past
                // four QR codes that are not theirs.
                ->assertDontSee($first->fresh()->pass_code);
        });

        unset($host);
    }

    public function test_a_traveller_with_no_pass_is_shown_but_cannot_be_opened(): void
    {
        [$host, $joiner, $reservation] = $this->paidBooking();

        // Added after the host tagged the money as in, so issueFor() has already run.
        $latecomer = ReservationParticipant::factory()->for($reservation)->create([
            'position' => 1, 'first_name' => 'Nina', 'last_name' => 'Diaz', 'birthday' => '1994-08-08',
        ]);
        $reservation->update(['party_size' => 2]);

        $this->browse(function (Browser $browser) use ($joiner, $reservation, $latecomer) {
            $browser->loginAs($joiner)
                ->visit('/r/'.$reservation->public_token)
                ->waitFor('@pass-tabs')
                // On the trip, so in the strip. Dropping them would read as "not coming".
                ->assertSeeIn('@pass-tabs', 'Nina')
                ->assertSeeIn('@pass-waiting', 'no pass yet');

            $inert = $browser->script(
                "const b = document.querySelector('[dusk=pass-tab-{$latecomer->id}]');
                 return [b.disabled, b.getAttribute('aria-disabled'), b.hasAttribute('wire:click')]"
            )[0];

            $this->assertTrue($inert[0], 'The passless tab is clickable.');
            $this->assertSame('true', $inert[1]);
            $this->assertFalse($inert[2], 'The passless tab still carries a wire:click.');
        });

        unset($host);
    }

    public function test_the_host_checks_a_traveller_in_by_typing_the_code_and_cannot_do_it_twice(): void
    {
        [$host, , $reservation, $departure] = $this->paidBooking();
        $code = $reservation->participants()->first()->pass_code;

        $this->browse(function (Browser $browser) use ($host, $departure, $code) {
            $browser->loginAs($host)
                ->visit('/host/departures/'.$departure->id.'/check-in')
                ->waitFor('@code-input')
                ->type('@code-input', $code)
                ->click('@check-in')
                ->waitForText('Ada Reyes')
                ->assertSeeIn('@scan-result', 'Ada Reyes');

            // Same code again. A second arrival is a mistake worth naming, not a silent no-op —
            // the host needs to know whether the person in front of them is already inside.
            $browser->type('@code-input', $code)
                ->click('@check-in')
                ->waitForText('already')
                ->assertSeeIn('@scan-result', 'already');
        });
    }

    public function test_a_code_from_another_trip_is_refused(): void
    {
        [$host, , $reservation] = $this->paidBooking();
        $code = $reservation->participants()->first()->pass_code;

        // A second departure belonging to the same host. The code is valid; it is simply not
        // valid *here*, which is the failure a host at a trailhead is most likely to hit.
        $other = TripDeparture::factory()
            ->for(Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Batulao Loop']))
            ->create(['capacity' => 8, 'starts_at' => now()->addWeeks(2)]);

        $this->browse(function (Browser $browser) use ($host, $other, $code) {
            $browser->loginAs($host)
                ->visit('/host/departures/'.$other->id.'/check-in')
                ->waitFor('@code-input')
                ->type('@code-input', $code)
                ->click('@check-in')
                ->waitFor('@scan-result')
                ->assertDontSeeIn('@scan-result', 'Ada Reyes');
        });
    }
}
