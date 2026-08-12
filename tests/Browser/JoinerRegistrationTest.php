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

class JoinerRegistrationTest extends DuskTestCase
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
            'host_id' => $host->id, 'title' => 'Mount Pulag Overnight', 'price' => 3500,
        ]);

        return TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);
    }

    public function test_a_stranger_registers_as_a_guest_and_reaches_their_booking(): void
    {
        $departure = $this->publishedDeparture();

        $this->browse(function (Browser $browser) use ($departure) {
            $browser->visit('/t/'.$departure->trip->slug.'/join')
                ->waitForText('Join this trip')
                ->type('@contact-first', 'Ada')
                ->type('@contact-last', 'Reyes')
                ->type('@contact-email', 'ada@example.com')
                ->type('@contact-phone', '09171234567')
                ->keys('@seat-0-birthday', '04', '02', '1998')
                ->type('@seat-0-ec-name', 'Rosa Reyes')
                ->type('@seat-0-ec-phone', '09171112222')
                ->uncheck('@create-account')
                ->click('@submit-registration')
                // the primary button opens the review sheet; the booking is confirmed inside it
                ->waitFor('@confirm-booking')
                ->click('@confirm-booking')
                ->waitForText('Save this link')
                ->assertVisible('@booking-link');
        });

        $reservation = Reservation::firstOrFail();

        $this->assertNull($reservation->joiner_id);
        $this->assertSame('ada@example.com', $reservation->contact_email);
        $this->assertCount(1, $reservation->participants);
        $this->assertTrue($reservation->participants->first()->is_booker);
        $this->assertSame('1998-04-02', $reservation->participants->first()->birthday->format('Y-m-d'));
    }

    public function test_leaving_the_toggle_on_creates_a_passwordless_account(): void
    {
        $departure = $this->publishedDeparture();

        $this->browse(function (Browser $browser) use ($departure) {
            $browser->visit('/t/'.$departure->trip->slug.'/join')
                ->waitForText('Join this trip')
                ->type('@contact-first', 'Ben')
                ->type('@contact-last', 'Cruz')
                ->type('@contact-email', 'ben@example.com')
                ->type('@contact-phone', '09998887777')
                ->keys('@seat-0-birthday', '01', '10', '1995')
                ->type('@seat-0-ec-name', 'Rosa Reyes')
                ->type('@seat-0-ec-phone', '09171112222')
                ->click('@submit-registration')
                // the primary button opens the review sheet; the booking is confirmed inside it
                ->waitFor('@confirm-booking')
                ->click('@confirm-booking')          // toggle left checked
                ->waitForText('Save this link');
        });

        $user = User::where('email', 'ben@example.com')->firstOrFail();

        $this->assertNull($user->password);          // signs in by emailed link only
        $this->assertSame('joiner', $user->role);
        $this->assertSame('Ben', $user->first_name);
    }

    public function test_a_booker_can_pay_for_others_without_taking_a_seat(): void
    {
        $departure = $this->publishedDeparture();

        $this->browse(function (Browser $browser) use ($departure) {
            $browser->visit('/t/'.$departure->trip->slug.'/join')
                ->waitForText('Join this trip')
                ->type('@contact-first', 'Cara')
                ->type('@contact-last', 'Lim')
                ->type('@contact-email', 'cara@example.com')
                ->type('@contact-phone', '09171112222')
                ->uncheck('@booker-traveling')
                ->waitForText('take no seat')
                ->type('@seat-0-first', 'Dee')
                ->type('@seat-0-last', 'Santos')
                // The one-tap answer to the per-seat email rule, exercised in a real browser:
                // a booker paying for someone else usually has their own inbox, not theirs.
                ->click('@seat-0-use-my-email')
                ->waitUsing(5, 100, fn () => $browser->value('@seat-0-email') === 'cara@example.com')
                ->keys('@seat-0-birthday', '03', '15', '2000')
                ->type('@seat-0-ec-name', 'Rosa Reyes')
                ->type('@seat-0-ec-phone', '09171112222')
                ->uncheck('@create-account')
                ->click('@submit-registration')
                // the primary button opens the review sheet; the booking is confirmed inside it
                ->waitFor('@confirm-booking')
                ->click('@confirm-booking')
                ->waitForText('Save this link');
        });

        $reservation = Reservation::firstOrFail();

        $this->assertSame('Cara Lim', $reservation->contactName());
        $this->assertFalse($reservation->bookerIsTraveling());
        $this->assertSame('Dee Santos', $reservation->participants->first()->fullName());
        $this->assertSame('2000-03-15', $reservation->participants->first()->birthday->format('Y-m-d'));
    }

    public function test_extra_seats_reveal_a_card_each(): void
    {
        $departure = $this->publishedDeparture();

        // Roomy, because the stepper now caps at seats remaining and the default capacity of 10
        // would clamp the two-digit case below. The cap has its own coverage in
        // RegisterComponentTest; what is under test here is the typing.
        $departure->update(['capacity' => 30]);

        $this->browse(function (Browser $browser) use ($departure) {
            $browser->visit('/t/'.$departure->trip->slug.'/join')
                ->waitForText('Join this trip')
                // One seat has no traveller cards at all now — the booker's own fields live in
                // "Your details". The cards are what extra seats reveal.
                ->assertMissing('@seat-1')
                ->assertMissing('@seat-2')
                // Real keystrokes, not clear()+type(): clear() sets the value without firing an
                // input event, so the empty-field state never reached the server and a 500 on
                // backspacing over the number went unnoticed by this test.
                // The pause is the point. Debouncing coalesces a fast backspace-then-digit, so
                // the empty value never reaches the server; someone who clears the box and thinks
                // for a moment does send "", which is what crashed the component.
                ->keys('@party-size', '{backspace}')
                ->pause(900)
                ->keys('@party-size', '3')
                ->waitFor('@seat-2', 10)
                ->assertVisible('@seat-1')
                ->assertVisible('@seat-2')
                // Two digits, typed one at a time — impossible if the field snaps to a clamped
                // value on every keystroke.
                ->keys('@party-size', '{backspace}', '1', '2')
                ->waitFor('@seat-11', 10)
                ->assertVisible('@seat-11');
        });
    }
}
