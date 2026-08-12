<?php

use App\Livewire\Reservations\Show;
use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

function showPageBooking(array $overrides = []): Reservation
{
    $host = User::factory()->create(['role' => 'host', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'price' => 2000]);
    $departure = TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

    return Reservation::factory()->for($departure, 'departure')->create(array_merge([
        'joiner_id' => null,
        // Confirmed by default: payment follows the seat being granted, so a pending booking
        // has no checkout on it at all and every payment-detail assertion would be vacuous.
        'status' => 'confirmed',
        'confirmed_at' => now(),
        'party_size' => 1,
        'contact_first_name' => 'Ada',
        'contact_last_name' => 'Reyes',
        'contact_email' => 'ada@example.com',
        'contact_phone' => '09171234567',
    ], $overrides));
}

it('shows every traveller detail, not just the name', function () {
    $reservation = showPageBooking();

    ReservationParticipant::factory()->for($reservation)->create([
        'position' => 1,
        'is_booker' => true,
        'first_name' => 'Ada',
        'last_name' => 'Reyes',
        'birthday' => '1998-04-02',
        'gender' => 'female',
        'email' => 'ada@example.com',
        'phone' => '09171234567',
    ]);

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->assertSee('Ada Reyes')
        ->assertSee('2 Apr 1998')      // birthday, not only the name
        ->assertSee('female')          // gender — capitalised by CSS, so the text stays lowercase
        ->assertSee('ada@example.com') // email
        ->assertSee('09171234567')     // mobile
        ->assertSee('Booking contact', false);
});

it('renders an age as a whole number', function () {
    // Carbon 3 returns a float from diffInYears — an uncast age renders as "27.4109y".
    $reservation = showPageBooking();

    ReservationParticipant::factory()->for($reservation)->create([
        'position' => 1,
        'first_name' => 'Ada',
        'last_name' => 'Reyes',
        'birthday' => now()->subYears(27)->subMonths(5)->toDateString(),
    ]);

    $html = Livewire::test(Show::class, ['token' => $reservation->public_token])->html();

    expect($html)->toContain('27y')
        ->and($html)->not->toMatch('/\d+\.\d+y/');
});

it('cancels the booking when the confirmation is taken', function () {
    // The reported bug: the dialog opened, closed, and left the reservation untouched, because
    // the confirm button rendered `$wire.cancel` — a property reference — instead of a call.
    $reservation = showPageBooking();

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('cancel')
        ->assertRedirect(route('home'));

    expect($reservation->fresh()->status)->toBe('cancelled');
});

it('wires the cancel confirmation to a call rather than a reference', function () {
    // Self-cancel only exists before the host accepts you — after that, withdrawing goes
    // through them. So this control lives on a pending booking by design.
    $reservation = showPageBooking(['status' => 'pending', 'confirmed_at' => null]);

    $html = Livewire::test(Show::class, ['token' => $reservation->public_token])->html();

    expect($html)->toContain('$wire.cancel()');
});

it('gives the edit modal an opaque surface and room to lay its fields out', function () {
    // It was `bg-cream/90` at `sm:max-w-xl`: the page showed through the labels and the
    // three-column name row ran past the panel edge.
    $reservation = showPageBooking();

    $participant = ReservationParticipant::factory()->for($reservation)->create([
        'position' => 1, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02',
    ]);

    $html = Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('editParticipant', $participant->id)
        ->assertSet('showEditModal', true)
        ->html();

    expect($html)->toContain('bg-surface')
        ->and($html)->toContain('sm:max-w-3xl')
        ->and($html)->not->toContain('sm:bg-cream/90');
});

it('still offers a way to pay after the seat is confirmed but nothing was paid', function () {
    // The reported bug: a host confirms a seat, the joiner's page says "You're confirmed —
    // nothing further to pay", and the proof-of-payment form is gone even though they owe the
    // whole amount. Confirming accepts the seat; it does not settle the bill.
    $reservation = showPageBooking(['status' => 'confirmed', 'confirmed_at' => now()]);

    // "still to pay" used to come from the confirmed banner, which has since been removed.
    // The surviving signals are the rail's outstanding figure and the checkout itself.
    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->assertSee('Still to pay')
        ->assertSee('Submit payment')
        ->assertSeeHtml('dusk="reference_no"')
        ->assertSeeHtml('dusk="pay-sheet"');
});

it('says nothing is left to pay once the host tags it fully paid', function () {
    $reservation = showPageBooking([
        'status' => 'confirmed', 'confirmed_at' => now(), 'fully_paid_at' => now(),
    ]);

    // Nothing owed means no checkout at all — the absence is the statement now that the
    // banner that used to say it in words is gone.
    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->assertDontSee('Submit payment')
        ->assertDontSeeHtml('dusk="pay-sheet"')
        ->assertDontSeeHtml('dusk="open-pay"');
});

it('keeps the seat confirmed when the balance is sent', function () {
    // Submitting the balance is not a fresh request for a place — flipping back to
    // payment_submitted would un-accept someone the host already let on the trip.
    $reservation = showPageBooking(['status' => 'confirmed', 'confirmed_at' => now()]);

    app(ReservationService::class)->submitPayment($reservation, ['reference_no' => '9021']);

    expect($reservation->fresh()->status)->toBe('confirmed')
        ->and($reservation->fresh()->paymentSubmissions()->count())->toBe(1);
});

it('shows the number, the account name and the QR for the chosen method', function () {
    $reservation = showPageBooking();
    $reservation->departure->trip->host->hostProfile->update([
        'payment_methods' => [[
            'label' => 'GCash', 'detail' => '0917 000 1111',
            'name' => 'Juan D. Cruz', 'qr' => 'qr/gcash.png',
        ]],
    ]);

    $html = Livewire::test(Show::class, ['token' => $reservation->public_token])->html();

    expect($html)->toContain('0917 000 1111')
        ->and($html)->toContain('Juan D. Cruz')      // the check before the money moves
        ->and($html)->toContain('Account name')
        // object-contain, never cover: a cropped QR can lose its alignment squares and stop
        // scanning altogether.
        ->and($html)->toContain('object-contain')
        ->and($html)->not->toContain('object-cover rounded-lg');
});

it('keeps the payment details on a plain white surface', function () {
    // Pine with cream text put the account number and the QR on the lowest-contrast surface
    // on the page — the two things a joiner has to read most carefully.
    $reservation = showPageBooking();
    $reservation->departure->trip->host->hostProfile->update([
        'payment_methods' => [['label' => 'GCash', 'detail' => '0917 000 1111']],
    ]);

    $html = Livewire::test(Show::class, ['token' => $reservation->public_token])->html();

    // The surface, not the spacing — padding is tuned per breakpoint and is nobody's guarantee.
    // The hook is the first attribute on that div precisely so this slice catches its classes.
    $panel = Str::of($html)->after('dusk="method-detail"')->before('>')->toString();

    // Scoped to the panel: `bg-pine text-cream` is a perfectly good button elsewhere on this
    // page, so a page-wide negative would be asserting the wrong thing.
    expect($panel)->not->toBeEmpty()
        // White, not the cream tint it had briefly: a payment record should read as a document,
        // and any wash of colour on it reads as promotion.
        ->and($panel)->toContain('bg-surface')
        ->and($panel)->not->toContain('bg-cream')
        ->and($panel)->not->toContain('bg-pine')
        ->and($panel)->not->toContain('text-cream');
});

it('routes the payment proof through the shared uploader', function () {
    $reservation = showPageBooking();

    $html = Livewire::test(Show::class, ['token' => $reservation->public_token])->html();

    expect($html)->toContain('Add a receipt')
        // the real input is still there, just not on show
        ->and($html)->toContain('type="file"')
        ->and($html)->toContain('sr-only');
});

it('holds the payment sheet open server-side, not in Alpine alone', function () {
    /*
     * Attaching a receipt is a Livewire round trip, and a plain `x-data="{ pay: false }"` is
     * re-initialised whenever the component re-renders — which is how the sheet slammed shut
     * mid-payment. Entangling it to a component property means the open state survives every
     * render an upload causes.
     *
     * This asserts the mechanism rather than the symptom: a Dusk attach() does not reproduce
     * the close locally, so a browser test of it would have passed either way.
     */
    $reservation = showPageBooking();

    $page = Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->assertSet('payOpen', false);

    expect($page->html())->toContain("entangle('payOpen')")
        ->and($page->html())->not->toContain('x-data="{ pay: false }"');

    // And the property is real state the server keeps, not decoration.
    $page->set('payOpen', true)->assertSet('payOpen', true);
});

it('acknowledges a receipt sent against a confirmed booking', function () {
    // Confirming keeps the status at `confirmed`, and an unverified submission credits nothing,
    // so submitting proof changed neither the status nor the money — the page redrew identically
    // and the joiner had no idea it had worked.
    $reservation = showPageBooking(['status' => 'confirmed', 'confirmed_at' => now()]);
    $reservation->departure->trip->host->hostProfile->update([
        'payment_methods' => [['label' => 'GCash', 'detail' => '0917 000 1111']],
    ]);

    $page = Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->set('reference_no', '9021 5567 3382')
        ->call('submit')
        ->assertHasNoErrors();

    expect($reservation->fresh()->paymentSubmissions()->count())->toBe(1);

    // Something on the page has to say it landed.
    $page->assertSee('waiting')
        ->assertDontSeeHtml('dusk="submit-proof"');
});

it('serves the receipt to whoever holds the booking token, and to nobody else', function () {
    $reservation = showPageBooking();
    $reservation->paymentSubmissions()->create([
        'reference_no' => '9021', 'submitted_at' => now(),
        'proof_image' => 'proofs/receipt.png',
    ]);
    Storage::disk('local')->put('proofs/receipt.png', 'png-bytes');

    $this->get(route('reservations.proof', ['token' => $reservation->public_token]))
        ->assertOk();

    // A guessed token is not a way in.
    $this->get(route('reservations.proof', ['token' => Str::uuid()->toString()]))
        ->assertNotFound();
});

it('404s the receipt route when nothing was uploaded', function () {
    $reservation = showPageBooking();
    $reservation->paymentSubmissions()->create(['reference_no' => '9021', 'submitted_at' => now()]);

    $this->get(route('reservations.proof', ['token' => $reservation->public_token]))
        ->assertNotFound();
});

it('turns the sent-receipt banner into its own sheet with what was submitted', function () {
    $reservation = showPageBooking();
    $reservation->paymentSubmissions()->create([
        'reference_no' => '9021 5567 3382', 'method' => 'GCash', 'submitted_at' => now(),
    ]);

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->assertSet('proofOpen', false)
        ->assertSeeHtml('dusk="open-proof"')    // the pinned bar on a phone
        ->assertSeeHtml('dusk="proof-sheet"')
        ->assertSee('9021 5567 3382')           // what was actually sent
        ->assertSee('GCash')
        ->assertSee('Reference');
});
