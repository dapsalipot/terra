<?php

use App\Exceptions\InvalidTransitionException;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ReservationService;

beforeEach(function () {
    $this->svc = app(ReservationService::class);
    $this->departure = TripDeparture::factory()->create(['capacity' => 14]);
    $this->joiner = User::factory()->create(['role' => 'joiner']);
    $this->host = User::factory()->create(['role' => 'host']);
});

it('walks the happy path: reserve -> confirm -> submit -> verify', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id, 'party_size' => 2]);
    expect($r->status)->toBe('pending')
        ->and($r->expires_at)->not->toBeNull();

    // The seat is granted first. Only then is there something to pay for.
    $this->svc->confirm($r->fresh(), $this->host);
    expect($r->fresh()->status)->toBe('confirmed')
        ->and($r->fresh()->expires_at)->toBeNull();

    $this->svc->submitPayment($r->fresh(), ['method' => 'Bank transfer', 'reference_no' => '9021']);
    // Sending money does not un-accept the seat, so the status holds.
    expect($r->fresh()->status)->toBe('confirmed')
        ->and($r->fresh()->money()['paid'])->toBe(0.0);

    $this->svc->verifyPayment($r->fresh(), $this->host);
    expect($r->fresh()->money()['paid'])->toBeGreaterThan(0.0);
});

it('refuses payment for a seat the host has not granted yet', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);

    // The whole point of the reordering: nobody sends money for a place that may not exist.
    $this->svc->submitPayment($r, ['reference_no' => '9021']);
})->throws(InvalidTransitionException::class);

it('rejecting a proof refuses the money, not the person', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    $this->svc->confirm($r->fresh(), $this->host);
    $this->svc->submitPayment($r->fresh(), ['reference_no' => 'x']);

    $this->svc->reject($r->fresh(), $this->host, 'Reference not found');

    $r->refresh();
    /*
     * The seat stays granted and no hold clock is re-armed. Dropping this back to
     * `awaiting_payment` with a fresh expires_at would hand a confirmed traveller to
     * expireStaleHolds(), which cancels bookings — so a mistyped bank reference would quietly
     * cost somebody their place on the trip.
     */
    expect($r->status)->toBe('confirmed')
        ->and($r->expires_at)->toBeNull()
        ->and($r->latestPayment->rejected_reason)->toBe('Reference not found')
        ->and($r->canSubmitPayment())->toBeTrue()
        ->and($this->departure->fresh()->heldCount())->toBe(1);
});

/*
 * A host can confirm a booking that never submitted anything.
 *
 * This used to throw: confirm() accepted `payment_submitted` alone, so a joiner who paid by
 * handing over cash, or by GCash the host saw in their own notifications, was a dead end on the
 * roster — no button, no way to mark them paid, and the money totals stayed wrong forever.
 * Payment happens outside this app by design, so the host's word is the only thing that can
 * settle it.
 */
it('lets a host mark a pending booking as paid without a submission', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    expect($r->status)->toBe('pending');

    $this->svc->confirm($r, $this->host);

    expect($r->fresh()->status)->toBe('confirmed')
        ->and($r->fresh()->expires_at)->toBeNull();
});

it('lets a joiner send a replacement after their proof was turned down', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    $this->svc->confirm($r->fresh(), $this->host);
    $this->svc->submitPayment($r->fresh(), ['reference_no' => 'wrong']);
    $this->svc->reject($r->fresh(), $this->host, 'Reference not found');

    $this->svc->submitPayment($r->fresh(), ['reference_no' => 'right']);
    $this->svc->verifyPayment($r->fresh(), $this->host);

    expect($r->fresh()->latestPayment->reference_no)->toBe('right')
        ->and($r->fresh()->hasVerifiedPayment())->toBeTrue();
});

/*
 * Confirming a seat is not a payment.
 *
 * This used to fabricate a submission reading "Marked paid by host" so the money totals had
 * something to hang off. That invented a payment record for a booking where nothing had been
 * paid, and — because money() credited it — accepting a seat settled the bill on any trip
 * without a deposit. The audit trail lives in confirmed_at, and the money trail in the
 * fully-paid tag that markFullyPaid() writes.
 */
it('does not invent a payment when confirming a booking that has none', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    expect($r->latestPayment)->toBeNull();

    $this->svc->confirm($r, $this->host);
    $fresh = $r->fresh();

    expect($fresh->status)->toBe('confirmed')
        ->and($fresh->confirmed_at)->not->toBeNull()
        ->and($fresh->paymentSubmissions()->count())->toBe(0)
        // Nothing was collected, so nothing is credited.
        ->and($fresh->money()['paid'])->toBe(0.0)
        ->and($fresh->isFullyPaid())->toBeFalse();
});

it('records who verified a payment and when', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    $this->svc->confirm($r->fresh(), $this->host);
    $this->svc->submitPayment($r->fresh(), ['method' => 'Bank transfer', 'reference_no' => '9021']);

    $this->svc->verifyPayment($r->fresh(), $this->host);

    // The only question that matters if a joiner later says they never paid.
    expect($r->fresh()->paymentSubmissions()->count())->toBe(1)
        ->and($r->fresh()->latestPayment->method)->toBe('Bank transfer')
        ->and($r->fresh()->latestPayment->confirmed_by)->toBe($this->host->id)
        ->and($r->fresh()->latestPayment->confirmed_at)->not->toBeNull();
});

it('refuses to verify a payment nobody sent', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    $this->svc->confirm($r->fresh(), $this->host);

    // Crediting money against no record at all is what markFullyPaid() is for — it writes one.
    $this->svc->verifyPayment($r->fresh(), $this->host);
})->throws(InvalidTransitionException::class);

it('cannot confirm a cancelled reservation', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    $this->svc->cancel($r, 'Change of plans');

    $this->svc->confirm($r->fresh(), $this->host);
})->throws(InvalidTransitionException::class);

it('cannot confirm a booking that is already confirmed', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    $this->svc->confirm($r, $this->host);

    $this->svc->confirm($r->fresh(), $this->host);
})->throws(InvalidTransitionException::class);

it('cancelling releases the seat', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id, 'party_size' => 3]);
    expect($this->departure->fresh()->heldCount())->toBe(3);

    $this->svc->cancel($r, 'Change of plans');
    expect($r->fresh()->status)->toBe('cancelled')
        ->and($this->departure->fresh()->heldCount())->toBe(0);
});

/*
 * ── Confirmed and fully paid are different facts ─────────────────────────────────────────
 */

it('credits only the deposit when a deposit is what was verified', function () {
    $trip = $this->departure->trip;
    $trip->update(['price' => 3000, 'deposit_amount' => 1000]);

    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id, 'party_size' => 2]);
    $this->svc->confirm($r->fresh(), $this->host);
    $this->svc->submitPayment($r->fresh(), ['reference_no' => '9021']);
    $this->svc->verifyPayment($r->fresh(), $this->host);

    // The deposit landed; the balance is still owed on the day.
    expect($r->fresh()->money())->toMatchArray(['total' => 6000.0, 'paid' => 2000.0, 'due' => 4000.0])
        ->and($r->fresh()->isFullyPaid())->toBeFalse()
        ->and($r->fresh()->owesBalance())->toBeTrue();
});

it('credits the whole amount once the host tags it fully paid', function () {
    $trip = $this->departure->trip;
    $trip->update(['price' => 3000, 'deposit_amount' => 1000]);

    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id, 'party_size' => 2]);
    $this->svc->confirm($r, $this->host);
    $this->svc->markFullyPaid($r->fresh(), $this->host);

    $r->refresh();
    expect($r->isFullyPaid())->toBeTrue()
        ->and($r->money())->toMatchArray(['total' => 6000.0, 'paid' => 6000.0, 'due' => 0.0])
        ->and($r->fully_paid_by)->toBe($this->host->id)
        ->and($r->fully_paid_at)->not->toBeNull();
});

/*
 * The undo the host asked for. Un-paying must not also un-confirm: those are separate decisions
 * and throwing away the seat approval because of a mis-tap on money would be worse than the
 * mistake being corrected.
 */
it('lets a host take a fully-paid tag back without un-confirming the seat', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    $this->svc->confirm($r, $this->host);
    $this->svc->markFullyPaid($r->fresh(), $this->host);
    expect($r->fresh()->isFullyPaid())->toBeTrue();

    $this->svc->unmarkFullyPaid($r->fresh());

    $r->refresh();
    expect($r->isFullyPaid())->toBeFalse()
        ->and($r->fully_paid_at)->toBeNull()
        ->and($r->fully_paid_by)->toBeNull()
        // still accepted — only the money claim was withdrawn
        ->and($r->status)->toBe('confirmed');
});

it('can be paid in full before the host has confirmed the seat', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);

    $this->svc->markFullyPaid($r, $this->host);

    expect($r->fresh()->isFullyPaid())->toBeTrue()
        ->and($r->fresh()->status)->toBe('pending');
});

it('stops counting a cancelled booking as paid', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    $this->svc->markFullyPaid($r, $this->host);
    $this->svc->cancel($r->fresh(), 'Changed plans');

    // The row keeps its history, but a cancelled seat must never inflate the takings.
    expect($r->fresh()->isFullyPaid())->toBeFalse()
        ->and($r->fresh()->money()['paid'])->toBe(0.0);
});

it('refuses to mark a cancelled booking as fully paid', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    $this->svc->cancel($r, 'Changed plans');

    $this->svc->markFullyPaid($r->fresh(), $this->host);
})->throws(InvalidTransitionException::class);
