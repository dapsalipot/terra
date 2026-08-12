<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * The joiner's home screen — the 60/40 split, the next trip, and the nudge to pay.
 *
 * The point of this page is triage: what is happening soonest, and what still needs money.
 * That is a layout claim as much as a data claim, so it is checked in a real viewport at both
 * widths rather than only asserting the component handed the view the right collection.
 */
class JoinerDashboardFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    /**
     * A joiner with one of each: a settled trip, one owing money, one awaiting the host.
     *
     * Three bookings rather than two because the lifecycle now separates "you owe money" from
     * "your host has not answered" — a single unconfirmed booking used to cover both and now
     * covers neither properly.
     */
    private function joinerWithBothKinds(): User
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $joiner = User::factory()->create(['role' => 'joiner', 'name' => 'Mika Ramos']);

        $soon = Trip::factory()->published()->create([
            'host_id' => $host->id, 'title' => 'Mount Pulag Traverse', 'price' => 2000,
            // Catalogue keys, not free text — the view resolves each to a label and an icon.
            'packing_items' => ['headlamp', 'jacket', 'water'],
        ]);
        $soonDeparture = TripDeparture::factory()->for($soon)->create([
            'capacity' => 10, 'starts_at' => now()->addDays(6),
        ]);
        $confirmed = Reservation::factory()->for($soonDeparture, 'departure')->create([
            'joiner_id' => $joiner->id, 'status' => 'confirmed', 'party_size' => 1,
            'contact_first_name' => 'Mika', 'contact_last_name' => 'Ramos', 'contact_email' => 'mika@example.com',
        ]);
        ReservationParticipant::factory()->for($confirmed)->create([
            'position' => 0, 'first_name' => 'Mika', 'last_name' => 'Ramos', 'birthday' => '1995-02-11',
        ]);

        $later = Trip::factory()->published()->create([
            'host_id' => $host->id, 'title' => 'Batulao Loop', 'price' => 900,
        ]);
        $laterDeparture = TripDeparture::factory()->for($later)->create([
            'capacity' => 10, 'starts_at' => now()->addMonth(),
        ]);
        // Granted but unpaid: this is what "for payment" means now.
        $owing = Reservation::factory()->for($laterDeparture, 'departure')->create([
            'joiner_id' => $joiner->id, 'status' => 'confirmed', 'confirmed_at' => now(), 'party_size' => 1,
            'contact_first_name' => 'Mika', 'contact_last_name' => 'Ramos', 'contact_email' => 'mika@example.com',
            'full_payment_due_at' => now()->addDays(5),
        ]);
        ReservationParticipant::factory()->for($owing)->create([
            'position' => 0, 'first_name' => 'Mika', 'last_name' => 'Ramos', 'birthday' => '1995-02-11',
        ]);

        // Still a request. Nothing to pay until the host answers it.
        $requested = Reservation::factory()->for(
            TripDeparture::factory()->for(Trip::factory()->published()->create([
                'host_id' => $host->id, 'title' => 'Taal Sunrise', 'price' => 700,
            ]))->create(['capacity' => 10, 'starts_at' => now()->addMonths(2)]),
            'departure'
        )->create([
            'joiner_id' => $joiner->id, 'status' => 'pending', 'party_size' => 1,
            'contact_first_name' => 'Mika', 'contact_last_name' => 'Ramos', 'contact_email' => 'mika@example.com',
            'expires_at' => now()->addDay(),
        ]);
        ReservationParticipant::factory()->for($requested)->create([
            'position' => 0, 'first_name' => 'Mika', 'last_name' => 'Ramos', 'birthday' => '1995-02-11',
        ]);

        return $joiner;
    }

    /** A trip that already ran, carrying the figures the month summary adds up. */
    private function completedHike(User $joiner): void
    {
        $host = User::where('role', 'host')->firstOrFail();
        $trip = Trip::factory()->published()->create([
            'host_id' => $host->id, 'title' => 'Pulag Ambangeg', 'price' => 2500,
            'inclusions' => ['Transport', 'Guide'],
        ]);
        $departure = TripDeparture::factory()->for($trip)->create([
            'capacity' => 10, 'starts_at' => now()->subMonth(), 'status' => 'completed',
        ]);
        $destination = $trip->destinations()->create([
            'place_id' => Place::factory()->create()->id, 'position' => 0,
        ]);
        $destination->hikeDetail()->create([
            'masl' => 2922, 'elevation_gain_m' => 1400, 'distance_km' => 14.5, 'duration_hours' => 9,
        ]);

        $r = Reservation::factory()->for($departure, 'departure')->create([
            'joiner_id' => $joiner->id, 'status' => 'confirmed', 'confirmed_at' => now()->subMonths(2),
            'fully_paid_at' => now()->subMonths(2), 'party_size' => 1,
            'contact_first_name' => 'Mika', 'contact_last_name' => 'Ramos', 'contact_email' => 'mika@example.com',
        ]);
        ReservationParticipant::factory()->for($r)->create([
            'position' => 0, 'first_name' => 'Mika', 'last_name' => 'Ramos', 'birthday' => '1995-02-11',
        ]);
    }

    public function test_the_dashboard_leads_with_the_soonest_trip_and_what_to_pack_for_it(): void
    {
        $joiner = $this->joinerWithBothKinds();

        $this->browse(function (Browser $browser) use ($joiner) {
            $browser->loginAs($joiner)
                ->visit('/my-trips')
                ->waitFor('@next-up')
                ->assertSeeIn('@next-up', 'Mount Pulag Traverse')
                // Soonest first. The section holds the headline card *and* the run of later
                // trips, so the trip a month out belongs in the small list underneath — the
                // claim being checked is which one got the headline, not which are present.
                ->assertSeeIn('@later-trips', 'Batulao Loop')
                ->assertDontSeeIn('@later-trips', 'Mount Pulag Traverse')
                ->assertSeeIn('@next-up', 'Mount Pulag Traverse')
                ->assertSeeIn('@what-to-bring', 'Headlamp')
                ->assertSeeIn('@what-to-bring', 'Rain jacket');
        });
    }

    public function test_an_unpaid_booking_is_surfaced_with_a_way_to_pay_it(): void
    {
        $joiner = $this->joinerWithBothKinds();

        $this->browse(function (Browser $browser) use ($joiner) {
            $browser->loginAs($joiner)
                ->visit('/my-trips')
                ->waitFor('@tab-pay')
                ->click('@tab-pay')
                ->waitFor('@awaiting-payment')
                ->assertSeeIn('@awaiting-payment', 'Batulao Loop')
                ->assertSeeIn('@awaiting-payment', 'Pay');
        });
    }

    public function test_the_split_stacks_on_a_phone_instead_of_squeezing_into_two_columns(): void
    {
        $joiner = $this->joinerWithBothKinds();

        $this->browse(function (Browser $browser) use ($joiner) {
            $browser->loginAs($joiner)
                ->resize(390, 844)
                ->visit('/my-trips')
                ->waitFor('@next-up');

            /*
             * Two columns at 390px would put the trip card at roughly half the viewport. The
             * assertion is on measured width rather than on a class name, because a Tailwind
             * breakpoint class can be present and still be overridden by something later.
             */
            $width = $browser->script("return document.querySelector('[dusk=\"next-up\"]').getBoundingClientRect().width")[0];

            $this->assertGreaterThan(300, $width, 'The next-up panel is still in a narrow column on a phone.');

            $browser->resize(1920, 1080);
        });
    }

    public function test_the_right_column_is_tabs_rather_than_a_stack(): void
    {
        $joiner = $this->joinerWithBothKinds();

        $this->browse(function (Browser $browser) use ($joiner) {
            $browser->loginAs($joiner)
                ->visit('/my-trips')
                ->waitFor('@panel-tabs')
                // One panel showing, the others behind their tabs.
                ->assertVisible('@next-up')
                ->assertMissing('@for-confirmation')
                ->click('@tab-pay')
                ->waitForText('Batulao Loop')
                ->assertMissing('@next-up');

            /*
             * The point of the change: four stacked sections on a 22rem sidebar put the thing you
             * needed below two things you did not. Whatever is showing has to start at the top of
             * the column, not several hundred pixels down it.
             */
            $top = $browser->script(
                "return Math.round(document.querySelector('[role=tabpanel]').getBoundingClientRect().top
                      - document.querySelector('[dusk=panel-tabs]').getBoundingClientRect().bottom)"
            )[0];

            $this->assertLessThan(60, $top, 'The open panel does not sit directly under its tabs.');
        });
    }

    public function test_exactly_one_tab_reads_as_selected(): void
    {
        $joiner = $this->joinerWithBothKinds();

        $this->browse(function (Browser $browser) use ($joiner) {
            $browser->loginAs($joiner)
                ->visit('/my-trips')
                ->waitFor('@panel-tabs');

            $selected = $browser->script("return document.querySelectorAll('[role=tab][aria-selected=true]').length")[0];
            $this->assertSame(1, $selected, 'A tablist with none or several selected reads as broken.');

            $browser->click('@tab-confirm')->pause(500);

            $stillOne = $browser->script("return document.querySelectorAll('[role=tab][aria-selected=true]').length")[0];
            $this->assertSame(1, $stillOne);
        });
    }

    public function test_the_open_tab_survives_a_reload_because_it_is_in_the_url(): void
    {
        $joiner = $this->joinerWithBothKinds();

        $this->browse(function (Browser $browser) use ($joiner) {
            $browser->loginAs($joiner)
                ->visit('/my-trips')
                ->waitFor('@panel-tabs')
                ->click('@tab-pay')
                ->waitForText('Batulao Loop')
                ->refresh()
                // Landing back on "next up" after a reload is how somebody loses the thing they
                // were part-way through paying.
                ->waitForText('Batulao Loop')
                ->assertQueryStringHas('panel', 'pay');
        });
    }

    public function test_the_calendar_comes_first_on_a_phone(): void
    {
        $joiner = $this->joinerWithBothKinds();

        $this->browse(function (Browser $browser) use ($joiner) {
            $browser->loginAs($joiner)
                ->resize(390, 844)
                ->visit('/my-trips')
                ->waitFor('@calendar');

            /*
             * Measured position, not source order: a `order-*` utility or a grid rule could put
             * the panels back on top without touching the DOM, and the claim is about what a
             * joiner sees when the page opens — not how the markup is arranged.
             */
            $tops = $browser->script(
                "return [document.querySelector('[dusk=calendar]').getBoundingClientRect().top,
                         document.querySelector('[dusk=panel-tabs]').getBoundingClientRect().top]"
            )[0];

            $this->assertLessThan(
                $tops[1], $tops[0],
                'The side panels sit above the calendar on a phone.'
            );

            $browser->resize(1920, 1080);
        });
    }

    public function test_the_side_panels_stay_on_the_right_on_a_desktop(): void
    {
        $joiner = $this->joinerWithBothKinds();

        $this->browse(function (Browser $browser) use ($joiner) {
            // The other half: reordering the DOM for the phone must not move anything at xl,
            // where the aside's explicit grid placement is what holds it in column two.
            $browser->loginAs($joiner)
                ->resize(1600, 1000)
                ->visit('/my-trips')
                ->waitFor('@calendar');

            $lefts = $browser->script(
                "return [document.querySelector('[dusk=calendar]').getBoundingClientRect().left,
                         document.querySelector('[dusk=panel-tabs]').getBoundingClientRect().left]"
            )[0];

            $this->assertLessThan($lefts[1], $lefts[0], 'The panels are no longer in the right-hand column.');

            $browser->resize(1920, 1080);
        });
    }

    public function test_the_view_switcher_sits_on_the_calendar_it_controls(): void
    {
        $joiner = $this->joinerWithBothKinds();

        $this->browse(function (Browser $browser) use ($joiner) {
            $browser->loginAs($joiner)
                ->visit('/my-trips')
                ->waitFor('@calendar');

            $gap = $browser->script(
                "return Math.round(document.querySelector('[dusk=calendar]').getBoundingClientRect().top
                      - document.querySelector('[dusk=view-calendar]').getBoundingClientRect().bottom)"
            )[0];

            // Directly above it. It used to live in the page header, a full column away from the
            // thing it changed.
            $this->assertGreaterThanOrEqual(0, $gap, 'The switcher is below the calendar.');
            $this->assertLessThan(80, $gap, 'The switcher is too far from the calendar to read as its control.');
        });
    }

    public function test_a_past_month_carries_its_own_totals(): void
    {
        $joiner = $this->joinerWithBothKinds();
        $this->completedHike($joiner);

        $this->browse(function (Browser $browser) use ($joiner) {
            $browser->loginAs($joiner)
                ->visit('/my-trips')
                ->waitFor('@past')
                /*
                 * Asserted on the figures, not the labels. Every label in this band is
                 * `text-transform: uppercase`, and getText() returns what is rendered rather than
                 * what is in the HTML — so "All time" comes back as "ALL TIME" and a
                 * source-accurate needle silently never matches.
                 */
                ->assertSeeIn('@past-totals', '1,400 m')
                ->assertSeeIn('@past-totals', '2,922 m')
                ->scrollIntoView('@past-month')
                ->assertSeeIn('@past-month', '14.5 km');
        });
    }

    public function test_every_control_on_this_page_is_thumb_sized(): void
    {
        $joiner = $this->joinerWithBothKinds();

        $this->browse(function (Browser $browser) use ($joiner) {
            $browser->loginAs($joiner)
                ->resize(390, 844)
                ->visit('/my-trips')
                ->waitFor('@calendar')
                ->pause(300);

            /*
             * Measured, because a class name is not a size. The view switcher was 40x38, the panel
             * tabs 42 tall and the month arrows 40 square — each one a plausible-looking utility
             * that happened to land under the floor, and none of it visible in the markup.
             *
             * The wordmark is excluded: it is 28px tall, which clears the 24px WCAG 2.2 sets for
             * pointer targets, and padding a logo to 44 pushes the whole header down.
             */
            $undersized = $browser->script(<<<'JS'
const out = [];
document.querySelectorAll('main button, main a[href], main [role=tab]').forEach(el => {
  const r = el.getBoundingClientRect();
  if (r.width === 0 || r.height === 0) return;
  if (r.height < 44 || r.width < 44) out.push((el.getAttribute('dusk') || el.textContent.trim().slice(0, 20)) + ' ' + Math.round(r.width) + 'x' + Math.round(r.height));
});
return [...new Set(out)];
JS)[0];

            $this->assertSame([], $undersized, 'Controls below the 44px touch floor: '.implode(', ', $undersized));

            $browser->resize(1920, 1080);
        });
    }
}
