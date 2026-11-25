<?php

use App\Livewire\Host\Roster;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->host = User::factory()->create(['role' => 'host']);
    $trip = Trip::factory()->create(['host_id' => $this->host->id]);
    $this->departure = TripDeparture::factory()->create([
        'trip_id' => $trip->id, 'starts_at' => now()->addMonth(), 'capacity' => 10,
    ]);
});

it('lists every traveller by name', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 2, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 0,
        'first_name' => 'Ada', 'last_name' => 'Reyes',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 1,
        'first_name' => 'Ben', 'last_name' => 'Cruz',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->set('mode', 'manifest')
        ->assertSee('Ada Reyes')
        ->assertSee('Ben Cruz');
});

it('marks each traveller whose details are still missing', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 3, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create(['reservation_id' => $reservation->id, 'position' => 0]);
    ReservationParticipant::factory()->blank()->create(['reservation_id' => $reservation->id, 'position' => 1]);
    ReservationParticipant::factory()->blank()->create(['reservation_id' => $reservation->id, 'position' => 2]);

    // A row per person, so the gap is marked on the two rows that actually have it rather
    // than counted in a badge that never said which of the three names it meant.
    $html = Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->set('mode', 'manifest')
        ->html();

    expect(substr_count($html, 'Details still needed'))->toBe(2);
});

it('shows a young traveller their age rather than a minor flag', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 0,
        'first_name' => 'Kid', 'last_name' => 'Cruz',
        'birthday' => now()->subYears(15)->format('Y-m-d'),
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->set('mode', 'manifest')
        ->assertSee('15y')
        // The flag was dropped at the host's request: the age says the same thing and says
        // which of the names it applies to.
        ->assertDontSee('minor');
});

it('ages a traveller against the departure date, not today', function () {
    // Departure is a month out, so a 17-year-old whose birthday falls next week is 18 on the
    // trip. Measuring from today would print 17 and misstate the manifest.
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 0,
        'first_name' => 'Nearly', 'last_name' => 'Adult',
        'birthday' => now()->subYears(18)->addWeek()->format('Y-m-d'),
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->set('mode', 'manifest')
        ->assertSee('18y')
        ->assertDontSee('17y');
});

it('marks a booking whose booker is not travelling', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 0, 'is_booker' => false,
        'first_name' => 'Dee', 'last_name' => 'Santos',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->set('mode', 'manifest')
        // Phrased as an attribution now that it sits inline under the traveller.
        ->assertSee('Booked by');
});

/*
 * ── Confirming payment from the roster ───────────────────────────────────────────────────
 */

it('offers a mark-as-paid button on a booking that submitted nothing', function () {
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'pending',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertSeeHtml('dusk="confirm-'.$r->id.'"')
        ->assertSee('Confirm seat')
        // Nothing was submitted, so there is nothing to send back.
        ->assertDontSeeHtml('dusk="reject-'.$r->id.'"');
});

it('confirms a seat without moving any money', function () {
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'pending',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->set('mode', 'manifest')
        ->call('confirm', $r->id);

    // The seat is accepted; nothing was paid, so nothing is credited and no payment record
    // is conjured up to make the totals look settled.
    expect($r->fresh()->status)->toBe('confirmed')
        ->and($r->fresh()->latestPayment)->toBeNull()
        ->and($r->fresh()->money()['due'])->toBeGreaterThan(0);
});

/*
 * The wording is the whole point of the distinction: reviewing something a joiner sent is
 * "confirm", asserting a payment nobody uploaded is "mark as paid".
 */
it('says confirm rather than mark-as-paid when there is a submission to review', function () {
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'payment_submitted',
    ]);
    $r->paymentSubmissions()->create(['method' => 'GCash', 'submitted_at' => now()]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertSee('Confirm seat')
        ->assertSeeHtml('dusk="reject-'.$r->id.'"');
});

it('links to the proof whenever one exists, including after confirming', function () {
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
    ]);
    $r->paymentSubmissions()->create([
        'method' => 'GCash', 'proof_image' => 'proofs/receipt.jpg', 'submitted_at' => now(),
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        // A host re-checking what they approved should not have to un-confirm to see it.
        ->assertSeeHtml('dusk="modal-proof-'.$r->id.'"')
        // The control now names how many attempts there are and opens the list.
        ->assertSee('1 proof');
});

it('says so plainly when a booking has no proof attached', function () {
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'pending',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertSee('No proof sent');
});

it('offers no payment actions on a cancelled booking', function () {
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'cancelled',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertDontSeeHtml('dusk="confirm-'.$r->id.'"');
});

/*
 * ── Confirmation gate ────────────────────────────────────────────────────────────────────
 */

it('puts a confirmation step in front of marking a booking paid', function () {
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'pending',
    ]);

    $html = Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->html();

    // The trigger opens a dialog rather than calling the method, so the wire:click that used
    // to sit on it must be gone — otherwise the modal is decoration over a live one-click action.
    expect($html)->not->toContain('wire:click="confirm('.$r->id.')"')
        ->and($html)->toContain('dusk="confirm-'.$r->id.'-yes"')
        ->and($html)->toContain('Confirm this booking?');
});

it('warns that marking paid without proof is on the host', function () {
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'pending',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertSee('uploaded no proof')
        ->assertSee('Only confirm if you have agreed the seat another way');
});

/*
 * ── Paid vs confirmed, search and filter ─────────────────────────────────────────────────
 */

it('shows the fully-paid tag and its date, with an undo', function () {
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
        'confirmed_at' => now()->subDays(3), 'fully_paid_at' => now()->subDay(),
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertSeeHtml('dusk="paid-chip-'.$r->id.'"')
        ->assertSeeHtml('dusk="paid-date-'.$r->id.'"')
        ->assertSee('Paid in full '.now()->subDay()->format('j M Y'))
        ->assertSee('Confirmed '.now()->subDays(3)->format('j M Y'))
        // the undo the host asked for, offered on the very row that needs correcting
        ->assertSeeHtml('dusk="unmark-paid-'.$r->id.'"')
        ->assertDontSeeHtml('dusk="mark-paid-'.$r->id.'"');
});

it('undoes a fully-paid tag without touching the confirmation', function () {
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
        'confirmed_at' => now()->subDay(), 'fully_paid_at' => now(),
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->set('mode', 'manifest')
        ->call('unmarkFullyPaid', $r->id);

    expect($r->fresh()->isFullyPaid())->toBeFalse()
        ->and($r->fresh()->status)->toBe('confirmed');
});

it('offers marking fully paid on a booking that is confirmed but still owing', function () {
    $this->departure->trip->update(['price' => 3000, 'deposit_amount' => 1000]);
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);
    // A verified deposit is what makes the chip say "Deposit paid" — confirming alone no
    // longer credits anything.
    $r->paymentSubmissions()->create([
        'reference_no' => '9021', 'submitted_at' => now(),
        'confirmed_at' => now(), 'confirmed_by' => $this->host->id,
    ]);

    // The money column carries the numbers now, so the chip no longer has to narrate them.
    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertSeeHtml('dusk="mark-paid-'.$r->id.'"')
        ->assertSee('2,000 due');
});

it('finds a booking by a traveller name, not just the booker', function () {
    $mine = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1,
        'status' => 'confirmed', 'joiner_id' => User::factory()->create(['name' => 'Ada Reyes'])->id,
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $mine->id, 'position' => 0,
        'first_name' => 'Zoë', 'last_name' => 'Santos',
    ]);
    Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1,
        'status' => 'confirmed', 'joiner_id' => User::factory()->create(['name' => 'Ben Cruz'])->id,
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->set('mode', 'manifest')
        ->set('search', 'zoë')
        ->assertSee('Ada Reyes')       // matched via the traveller on the booking
        ->assertDontSee('Ben Cruz');
});

it('filters to bookings that are not fully paid', function () {
    // A deposit trip, so a confirmed booking genuinely still owes its balance. Without a deposit
    // confirming settles the whole price and nobody is "not fully paid" at all.
    $this->departure->trip->update(['price' => 3000, 'deposit_amount' => 1000]);

    $paid = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1,
        'status' => 'confirmed', 'fully_paid_at' => now(), 'joiner_id' => User::factory()->create(['name' => 'Paid Person'])->id,
    ]);
    $owing = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1,
        'status' => 'confirmed', 'joiner_id' => User::factory()->create(['name' => 'Owing Person'])->id,
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('setFilter', 'unpaid')
        ->assertSee('Owing Person')
        ->assertDontSee('Paid Person');
});

/*
 * The totals answer "how is this departure doing". They must not follow the filter, or money
 * appears to vanish the moment a host narrows the list.
 */
it('keeps the money totals on the whole roster while a filter is applied', function () {
    $this->departure->trip->update(['price' => 1000, 'deposit_amount' => null]);
    Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1,
        'status' => 'confirmed', 'fully_paid_at' => now(), 'joiner_id' => User::factory()->create(['name' => 'Paid Person'])->id,
    ]);
    Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1,
        'status' => 'pending', 'joiner_id' => User::factory()->create(['name' => 'Owing Person'])->id,
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('setFilter', 'paid')
        ->assertDontSee('Owing Person')
        // 2 seats x 1000 expected across the whole departure, even though one row is hidden
        ->assertSee('2,000');
});

it('ignores a filter it does not recognise instead of hiding everyone', function () {
    Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1,
        'status' => 'confirmed', 'joiner_id' => User::factory()->create(['name' => 'Ada Reyes'])->id,
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->set('mode', 'manifest')
        ->set('filter', 'nonsense')     // as a stray ?filter= would arrive
        ->assertSee('Ada Reyes');
});

it('shows each traveller age instead of a minor count on the booking', function () {
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 2, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $r->id, 'position' => 0,
        'first_name' => 'Ada', 'last_name' => 'Reyes',
        'birthday' => $this->departure->starts_at->copy()->subYears(34),
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $r->id, 'position' => 1,
        'first_name' => 'Kid', 'last_name' => 'Reyes',
        'birthday' => $this->departure->starts_at->copy()->subYears(12),
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->set('mode', 'manifest')
        ->assertSee('34y')
        ->assertSee('12y')
        // the old badge said a minor existed but never which of the names it was
        ->assertDontSee('guardian consent')
        ->assertDontSee('minor');
});

/*
 * On a trip with no deposit, confirming credits the whole price. The chip used to read
 * "Deposit paid · PHP 0 due" in warning amber — warning about a debt that did not exist.
 */
/*
 * This test used to assert the opposite, and it was wrong.
 *
 * On a trip with no deposit `payable_now` is the whole price, so crediting it on confirm made
 * accepting a seat settle the bill: the roster jumped to "Fully paid" the instant a host
 * confirmed someone who had paid nothing, and the two host actions collapsed into one.
 */
it('does not settle the price just because the seat was confirmed', function () {
    $this->departure->trip->update(['price' => 1000, 'deposit_amount' => null]);
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1,
        'status' => 'confirmed', 'confirmed_at' => now(),
    ]);

    expect($r->fresh()->money())->toMatchArray(['total' => 1000.0, 'paid' => 0.0, 'due' => 1000.0]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertDontSee('Fully paid ')
        // the host still has the second decision to make
        ->assertSeeHtml('dusk="mark-paid-'.$r->id.'"');
});

it('reads as fully paid once the host says the money is in', function () {
    $this->departure->trip->update(['price' => 1000, 'deposit_amount' => null]);
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1,
        'status' => 'confirmed', 'confirmed_at' => now(),
        'fully_paid_at' => now(), 'fully_paid_by' => $this->host->id,
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertSee('Fully paid')
        ->assertDontSee('Deposit paid')
        // and no button that would change nothing
        ->assertDontSeeHtml('dusk="mark-paid-'.$r->id.'"');
});

it('puts the payment record inside the mark-fully-paid confirmation', function () {
    // A money decision should be made looking at the reference, the amount and the receipt —
    // not recalled from the row behind the dialog.
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);
    $r->paymentSubmissions()->create([
        'reference_no' => '9021 5567 3382', 'method' => 'GCash',
        'submitted_at' => now(), 'proof_image' => 'proofs/receipt.png',
    ]);

    $html = Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertSeeHtml('dusk="mark-paid-'.$r->id.'"')
        ->html();

    expect($html)->toContain('9021 5567 3382')
        ->and($html)->toContain('GCash')
        ->and($html)->toContain('This marks as received')
        // and the receipt is shown, not merely named
        ->and($html)->toContain('dusk="modal-proof-'.$r->id.'"');
});

it('says plainly when there is no receipt to look at', function () {
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
        'confirmed_at' => now(),
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertSee('No receipt was uploaded')
        ->assertDontSeeHtml('dusk="modal-proof-'.$r->id.'"');
});

it('flags a receipt nobody has ruled on yet', function () {
    // Confirming leaves a booking at `confirmed` and an unverified submission credits nothing,
    // so a new upload against an already-confirmed booking left no trace on this page at all.
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1,
        'status' => 'confirmed', 'confirmed_at' => now(),
    ]);
    $r->paymentSubmissions()->create(['reference_no' => '9021', 'submitted_at' => now()]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertSeeHtml('dusk="new-proof-'.$r->id.'"')
        ->assertSee('New proof');
});

it('drops the flag once the host has ruled on that receipt', function () {
    $confirmed = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
    ]);
    $confirmed->paymentSubmissions()->create([
        'reference_no' => '9021', 'submitted_at' => now(),
        'confirmed_at' => now(), 'confirmed_by' => $this->host->id,
    ]);

    $rejected = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'awaiting_payment',
    ]);
    $rejected->paymentSubmissions()->create([
        'reference_no' => '7777', 'submitted_at' => now(), 'rejected_reason' => 'Not found',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $confirmed->id)
        ->assertDontSeeHtml('dusk="new-proof-'.$confirmed->id.'"')
        ->assertDontSeeHtml('dusk="new-proof-'.$rejected->id.'"');
});

it('lists every payment attempt with its own verdict', function () {
    // Rejected-then-resent is exactly the history that explains the current state, and only ever
    // linking the newest submission hid it.
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
    ]);
    $rejected = $r->paymentSubmissions()->create([
        'reference_no' => 'FIRST-1111', 'submitted_at' => now()->subDays(2),
        'rejected_reason' => 'Reference not found',
    ]);
    $accepted = $r->paymentSubmissions()->create([
        'reference_no' => 'SECOND-2222', 'submitted_at' => now(),
        'confirmed_at' => now(), 'confirmed_by' => $this->host->id,
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertSee('2 proofs')
        ->call('showProofs', $r->id)
        ->assertSeeHtml('dusk="proof-modal"')
        ->assertSeeHtml('dusk="proof-row-'.$rejected->id.'"')
        ->assertSeeHtml('dusk="proof-row-'.$accepted->id.'"')
        ->assertSee('FIRST-1111')
        ->assertSee('SECOND-2222')
        ->assertSee('Reference not found')       // why the first one failed
        ->assertSee('Rejected')
        ->assertSee('Confirmed')
        ->call('closeProofs')
        ->assertDontSeeHtml('dusk="proof-modal"');
});

it('shows the roster the traveller own contact, not the booker s', function () {
    // This is the roster: the row is the person travelling, so their details win. The booking
    // contact is the fallback for a seat someone booked on another person's behalf.
    $booker = User::factory()->create(['name' => 'Booker Bea', 'email' => 'bea@example.test']);
    $r = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1,
        'status' => 'confirmed', 'joiner_id' => $booker->id,
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $r->id, 'position' => 0, 'is_booker' => false,
        'first_name' => 'Dee', 'last_name' => 'Santos',
        'email' => 'dee@example.test', 'phone' => '09170001111',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->call('openBooking', $r->id)
        ->assertSee('Dee Santos')
        ->assertSee('dee@example.test')
        ->assertSee('09170001111');
});

// ─────────────────────────────────────────────────── emergency contact on the manifest

it('shows each traveller their own emergency contact on the manifest row', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 2, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes',
        'emergency_contact_name' => 'Rosa Reyes', 'emergency_contact_phone' => '09171112222',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 1, 'first_name' => 'Ben', 'last_name' => 'Cruz',
        'emergency_contact_name' => 'Lito Cruz', 'emergency_contact_phone' => '09173334444',
    ]);

    // Not the booker's: each person's own, because the one to ring is whoever is on the ground.
    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->set('mode', 'manifest')
        ->assertSee('Rosa Reyes')->assertSee('09171112222')
        ->assertSee('Lito Cruz')->assertSee('09173334444');
});

it('says outright when a traveller has nobody to call', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
    ]);
    // A booking made before emergency contacts were asked for.
    ReservationParticipant::factory()->withoutEmergencyContact()->create([
        'reservation_id' => $reservation->id, 'position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes',
    ]);

    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->set('mode', 'manifest')
        ->assertSee('No emergency contact');
});

it('keeps the emergency contact off the money rows so they stay one shape', function () {
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 1, 'status' => 'confirmed',
    ]);
    ReservationParticipant::factory()->create([
        'reservation_id' => $reservation->id, 'position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes',
        'emergency_contact_name' => 'Rosa Reyes', 'emergency_contact_phone' => '09171112222',
    ]);

    // Money mode is the default. A row carrying a variable amount of text is what made the old
    // page look like it had no grid.
    Livewire::actingAs($this->host)
        ->test(Roster::class, ['departure' => $this->departure])
        ->assertDontSee('In an emergency');
});
