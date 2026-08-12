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

class PaymentSheetTest extends DuskTestCase
{
    use DatabaseTruncation;

    private function booking(): Reservation
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create([
            'currency' => 'PHP',
            'payment_methods' => [['label' => 'GCash', 'detail' => '0917 000 1111', 'name' => 'Juan Cruz']],
        ]);
        $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'price' => 2000]);
        $departure = TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

        return Reservation::factory()->for($departure, 'departure')->create([
            // Confirmed: the checkout only exists on a seat the host has granted. A pending
            // booking has no payment UI at all, so every assertion below would find nothing.
            'joiner_id' => null, 'status' => 'confirmed', 'confirmed_at' => now(), 'party_size' => 1,
            'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes',
            'contact_email' => 'ada@example.com', 'contact_phone' => '09171234567',
        ]);
    }

    /** The checkout runs several screens long; on a phone it lives in a sheet behind one button. */
    public function test_the_payment_sheet_opens_and_closes_on_a_phone(): void
    {
        $reservation = $this->booking();

        $this->browse(function (Browser $browser) use ($reservation) {
            $browser->resize(390, 844)
                ->visit('/r/'.$reservation->public_token)
                ->waitFor('@open-pay')
                // Parked off-screen until asked for.
                ->assertScript('document.querySelector(\'[dusk="pay-sheet"]\').getBoundingClientRect().top >= window.innerHeight - 1')
                ->click('@open-pay')
                ->pause(700)
                // On screen, and tall enough to be the sheet rather than a sliver.
                ->assertScript('document.querySelector(\'[dusk="pay-sheet"]\').getBoundingClientRect().top < window.innerHeight - 200')
                ->assertScript('getComputedStyle(document.querySelector(\'[dusk="pay-sheet"]\')).visibility === "visible"')
                ->assertVisible('@submit-proof')
                ->click('@close-pay')
                ->pause(700)
                ->assertScript('document.querySelector(\'[dusk="pay-sheet"]\').getBoundingClientRect().top >= window.innerHeight - 1');
        });
    }

    /** From lg it is an ordinary card again — no launcher, nothing fixed. */
    public function test_the_checkout_stays_inline_on_a_desktop(): void
    {
        $reservation = $this->booking();

        $this->browse(function (Browser $browser) use ($reservation) {
            $browser->resize(1440, 900)
                ->visit('/r/'.$reservation->public_token)
                ->waitFor('@pay-sheet')
                ->assertScript('getComputedStyle(document.querySelector(\'[dusk="pay-sheet"]\')).position === "static"')
                ->assertScript('getComputedStyle(document.querySelector(\'[dusk="pay-sheet"]\')).visibility === "visible"')
                ->assertMissing('@open-pay');
        });
    }

    /** The whole point of the button: press it and the page says the receipt landed. */
    public function test_submitting_a_receipt_acknowledges_it_and_closes_the_sheet(): void
    {
        $reservation = $this->booking();

        $this->browse(function (Browser $browser) use ($reservation) {
            $browser->resize(390, 844)
                ->visit('/r/'.$reservation->public_token)
                ->waitFor('@open-pay')
                ->click('@open-pay')
                ->pause(600)
                ->type('@reference_no', '9021 5567 3382')
                ->click('@submit-proof')
                ->waitForText('Proof sent')
                ->assertSee('9021 5567 3382')
                // nothing left to do in the sheet, so it gets out of the way
                ->assertMissing('@open-pay');
        });
    }

    /** After submitting, the status is a pinned bar that opens what was sent. */
    public function test_the_sent_receipt_opens_as_a_sheet_on_a_phone(): void
    {
        $reservation = $this->booking();

        $this->browse(function (Browser $browser) use ($reservation) {
            $browser->resize(390, 844)
                ->visit('/r/'.$reservation->public_token)
                ->waitFor('@open-pay')
                ->click('@open-pay')
                ->pause(600)
                ->type('@reference_no', '9021 5567 3382')
                ->click('@submit-proof')
                ->waitFor('@open-proof')
                // parked until asked for
                ->assertScript('document.querySelector(\'[dusk="proof-sheet"]\').getBoundingClientRect().top >= window.innerHeight - 1')
                ->click('@open-proof')
                ->pause(700)
                ->assertScript('getComputedStyle(document.querySelector(\'[dusk="proof-sheet"]\')).visibility === "visible"')
                ->assertSee('9021 5567 3382')
                ->click('@close-proof')
                ->pause(700)
                ->assertScript('document.querySelector(\'[dusk="proof-sheet"]\').getBoundingClientRect().top >= window.innerHeight - 1');
        });
    }

    /**
     * Both bars hover, rather than sticking.
     *
     * `sticky bottom-0` only holds while its containing block still has room below it, so each
     * bar scrolled away once the page continued past its own section — exactly where a joiner
     * still needs it.
     */
    public function test_the_pinned_bars_stay_on_screen_at_the_bottom_of_the_page(): void
    {
        $reservation = $this->booking();

        $this->browse(function (Browser $browser) use ($reservation) {
            $browser->resize(390, 844)
                ->visit('/r/'.$reservation->public_token)
                ->waitFor('@open-pay')
                ->script('window.scrollTo(0, document.body.scrollHeight)');
            $browser->pause(400)
                ->assertScript('Math.round(document.querySelector(\'[dusk="open-pay"]\').closest("div").getBoundingClientRect().bottom) === window.innerHeight');

            // and again once the receipt bar has replaced it
            $browser->click('@open-pay')->pause(600)
                ->type('@reference_no', '9021 5567 3382')
                ->click('@submit-proof')
                ->waitFor('@open-proof')
                ->script('window.scrollTo(0, 0)');
            $browser->pause(400)
                ->assertScript('Math.round(document.querySelector(\'[dusk="open-proof"]\').getBoundingClientRect().bottom) === window.innerHeight');
        });
    }
}
