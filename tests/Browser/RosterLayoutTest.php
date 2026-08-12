<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\PaymentSubmission;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * The claims the redesign was actually for.
 *
 * "It's all over the place" is a measurable statement: rows of different heights, controls under
 * the touch floor, a page that scrolls sideways on a phone. Every assertion here is a measurement,
 * because the previous passes at this page all looked fine in the markup and wrong on screen.
 */
class RosterLayoutTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** Five bookings, deliberately in five different states — the spread that broke the old page. */
    private function messyDeparture(): array
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'rl', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $trip = Trip::factory()->published()->create([
            'host_id' => $host->id, 'title' => 'Mount Batur Sunrise', 'price' => 5741,
        ]);
        $departure = TripDeparture::factory()->for($trip)->create([
            'capacity' => 13, 'starts_at' => now()->addWeek(),
        ]);

        $states = [
            ['status' => 'confirmed', 'confirmed_at' => now(), 'proof' => true],
            ['status' => 'confirmed', 'confirmed_at' => now(), 'fully_paid_at' => now()],
            ['status' => 'confirmed', 'confirmed_at' => now()],
            ['status' => 'cancelled', 'cancelled_at' => now(), 'refund_due' => 1722],
            ['status' => 'pending'],
        ];

        foreach ($states as $i => $spec) {
            $proof = $spec['proof'] ?? false;
            unset($spec['proof']);

            $r = Reservation::factory()->for($departure, 'departure')->create(array_merge([
                'joiner_id' => null, 'party_size' => 1,
                'contact_first_name' => 'Trav'.$i, 'contact_last_name' => 'Cruz',
                'contact_email' => 'trav'.$i.'@example.com', 'contact_phone' => '0917111222'.$i,
            ], $spec));

            ReservationParticipant::factory()->for($r)->create([
                'position' => 0, 'first_name' => 'Trav'.$i, 'last_name' => 'Cruz', 'birthday' => '1995-02-11',
            ]);

            if ($proof) {
                PaymentSubmission::factory()->for($r)->create([
                    'reference_no' => 'GC-8821'.$i, 'method' => 'GCash', 'submitted_at' => now(),
                ]);
            }
        }

        return [$host, $departure];
    }

    public function test_every_row_is_the_same_height_whatever_state_it_is_in(): void
    {
        [$host, $departure] = $this->messyDeparture();

        $this->browse(function (Browser $browser) use ($host, $departure) {
            $browser->loginAs($host)
                ->resize(1440, 900)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->waitFor('@roster')
                ->pause(300);

            /*
             * The whole point. A booking with a proof waiting used to render six controls and a
             * text input; its settled neighbour rendered one button. No spacing system survives
             * that, which is why every previous restyle of this page failed.
             */
            $heights = $browser->script(
                "return Array.from(document.querySelectorAll('li[dusk^=\"reservation-\"]'))
                 .map(el => Math.round(el.getBoundingClientRect().height))"
            )[0];

            $this->assertGreaterThanOrEqual(5, count($heights), 'Expected a row per booking.');

            /*
             * One pixel of tolerance, and one only: `divide-y` puts a hairline above every row
             * except the first, so a perfectly uniform list still measures 76, 77, 77, 77. Any
             * real difference is a wrapped line, which is 12px or more — the failure this test
             * exists to catch, and what made the old page look like it had no grid at all.
             */
            $this->assertLessThanOrEqual(
                1, max($heights) - min($heights),
                'Rows differ in height: '.implode(', ', $heights)
            );
        });
    }

    public function test_the_page_does_not_scroll_sideways_on_a_phone(): void
    {
        [$host, $departure] = $this->messyDeparture();

        $this->browse(function (Browser $browser) use ($host, $departure) {
            foreach (['money', 'manifest', 'checkin'] as $mode) {
                $browser->loginAs($host)
                    ->resize(375, 812)
                    ->visit('/host/departures/'.$departure->id.'/roster?mode='.$mode)
                    ->waitFor('@roster')
                    ->pause(300);

                $overflows = $browser->script(
                    'return document.documentElement.scrollWidth > document.documentElement.clientWidth'
                )[0];

                $this->assertFalse($overflows, "The roster scrolls sideways at 375px in {$mode} mode.");
            }

            $browser->resize(1920, 1080);
        });
    }

    public function test_every_control_clears_the_touch_floor(): void
    {
        [$host, $departure] = $this->messyDeparture();

        $this->browse(function (Browser $browser) use ($host, $departure) {
            $browser->loginAs($host)
                ->resize(375, 812)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->waitFor('@roster')
                ->pause(300);

            // Measured, because a class name is not a size.
            $small = $browser->script(<<<'JS'
const out = [];
document.querySelectorAll('main button, main a[href]').forEach(el => {
  const r = el.getBoundingClientRect();
  if (r.width === 0 || r.height === 0) return;
  if (r.height < 44 || r.width < 44) out.push((el.getAttribute('dusk') || el.textContent.trim().slice(0, 18)) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height));
});
return [...new Set(out)];
JS)[0];

            $this->assertSame([], $small, 'Under the 44px floor: '.implode(', ', $small));

            $browser->resize(1920, 1080);
        });
    }

    public function test_each_mode_offers_only_the_filters_that_mode_can_use(): void
    {
        [$host, $departure] = $this->messyDeparture();

        $this->browse(function (Browser $browser) use ($host, $departure) {
            $browser->loginAs($host)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->waitFor('@filter-unpaid')
                // "Fully paid" is noise while checking people in, and "arrived" is meaningless
                // while reconciling transfers.
                ->assertMissing('@filter-arrived')
                ->click('@mode-checkin')
                ->waitFor('@filter-arrived')
                ->assertMissing('@filter-unpaid');
        });
    }

    public function test_the_drawer_keeps_the_list_on_screen(): void
    {
        [$host, $departure] = $this->messyDeparture();
        $first = $departure->reservations()->orderBy('id')->first();

        $this->browse(function (Browser $browser) use ($host, $departure, $first) {
            $browser->loginAs($host)
                ->resize(1600, 1000)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->waitFor('@roster')
                ->click('@open-'.$first->id)
                ->waitFor('@roster-drawer')
                // A run of proofs is worked one after another, so losing the list to a modal
                // would mean open-close-open-close all the way down.
                ->assertVisible('@roster-search')
                ->assertVisible('@reservation-'.$first->id);

            $browser->resize(1920, 1080);
        });
    }

    public function test_accepting_a_payment_is_a_form_not_a_yes_no(): void
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'rl2', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Group Trip', 'price' => 1000]);
        $departure = TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

        $r = Reservation::factory()->for($departure, 'departure')->create([
            'joiner_id' => null, 'party_size' => 3, 'status' => 'confirmed', 'confirmed_at' => now(),
            'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes', 'contact_email' => 'ada@example.com',
        ]);
        $people = collect(['Ada', 'Ben', 'Cee'])->map(fn ($n, $i) => ReservationParticipant::factory()
            ->for($r)->create(['position' => $i, 'first_name' => $n, 'last_name' => 'Cruz']));
        PaymentSubmission::factory()->for($r)->create([
            'reference_no' => 'GC-1', 'method' => 'GCash', 'submitted_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($host, $departure, $r, $people) {
            $browser->loginAs($host)
                ->resize(1600, 1000)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->waitFor('@roster')
                // The list says at a glance that this is a group and how much of it has paid.
                // Rendered case, not source case: the label is text-transform: uppercase, and
                // getText() returns what is on screen.
                ->assertSeeIn('@reservation-'.$r->id, 'GROUP OF 3')
                ->assertSeeIn('@reservation-'.$r->id, '0/3 paid')
                ->click('@open-'.$r->id)
                ->waitFor('@verify-'.$r->id)
                ->click('@verify-'.$r->id)
                ->waitFor('@pay-form')
                // Pre-filled with what is owed, everyone ticked.
                ->assertInputValue('@pay-amount', '3000');

            // Two of the three sent their share; the host unticks the one who did not.
            $browser->uncheck('@pay-for-'.$people[2]->id)
                ->clear('@pay-amount')
                ->type('@pay-amount', '2000')
                ->click('@pay-confirm')
                ->waitUntilMissing('@pay-form')
                ->waitForTextIn('@reservation-'.$r->id, '2/3 paid');
        });

        expect($r->fresh()->money()['due'])->toBe(1000.0)
            ->and($people[0]->fresh()->pass_code)->not->toBeNull()
            ->and($people[2]->fresh()->pass_code)->toBeNull();
    }
}
