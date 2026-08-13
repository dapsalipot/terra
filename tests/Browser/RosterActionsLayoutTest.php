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

/** Reject and Mark-fully-paid must be visually distinguishable, not just structurally different. */
class RosterActionsLayoutTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_reject_and_mark_fully_paid_do_not_share_a_visual_style(): void
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'act', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Trip', 'price' => 1000]);
        $departure = TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);
        $r = Reservation::factory()->for($departure, 'departure')->create([
            'joiner_id' => null, 'party_size' => 1, 'status' => 'confirmed', 'confirmed_at' => now(),
            'contact_first_name' => 'Jo', 'contact_last_name' => 'Reyes', 'contact_email' => 'jo@example.com',
        ]);
        ReservationParticipant::factory()->for($r)->create(['position' => 0, 'first_name' => 'Jo', 'last_name' => 'Reyes']);
        PaymentSubmission::factory()->for($r)->create(['reference_no' => 'X', 'method' => 'GCash', 'submitted_at' => now()]);

        $this->browse(function (Browser $browser) use ($host, $departure, $r) {
            $browser->loginAs($host)
                ->resize(1600, 1000)
                ->visit('/host/departures/'.$departure->id.'/roster')
                ->waitFor('@roster')
                ->click('@open-'.$r->id)
                ->waitFor('@reject-'.$r->id)
                ->waitFor('@mark-paid-'.$r->id);

            $colors = $browser->script(
                "return [
                    getComputedStyle(document.querySelector('[dusk=reject-".$r->id."]')).color,
                    getComputedStyle(document.querySelector('[dusk=mark-paid-".$r->id."]')).color
                ]"
            )[0];

            $this->assertNotSame(
                $colors[0], $colors[1],
                'Reject payment and Mark fully paid render in the same text color.'
            );

            $gap = $browser->script(
                "return document.querySelector('[dusk=mark-paid-".$r->id."]').getBoundingClientRect().top
                     - document.querySelector('[dusk=reject-".$r->id."]').getBoundingClientRect().bottom"
            )[0];

            // A label plus spacing sits between the two bands, so they read as separate groups
            // rather than a stacked pair of the same control.
            $this->assertGreaterThan(20, $gap, 'Reject and Mark fully paid sit too close to read as separate.');
        });
    }
}
