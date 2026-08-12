<?php

use App\Livewire\Host\Roster;
use App\Livewire\Reservations\Show;
use App\Models\HostProfile;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ReservationService;
use App\Support\PaymentTerms;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

function deadlineTrip(array $tripAttrs = []): array
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(array_merge([
        'host_id' => $host->id, 'title' => 'Mount Pulag Traverse', 'price' => 3000,
    ], $tripAttrs));
    $departure = TripDeparture::factory()->for($trip)->create([
        'capacity' => 10, 'starts_at' => now()->addMonth(),
    ]);

    return [$host, $trip, $departure];
}

/*
 * ── The terms themselves ────────────────────────────────────────────────────────────────────
 */

it('turns hours after confirmation into a real date', function () {
    $confirmed = Carbon::parse('2026-08-12 09:00:00');

    expect(PaymentTerms::resolve(48, null, $confirmed)->toDateTimeString())
        ->toBe('2026-08-14 09:00:00');
});

it('honours a fixed date over a relative one', function () {
    $fixed = Carbon::parse('2026-09-01 23:59:59');

    // Both set is not something the form allows, but data can always arrive another way. The
    // whole group being told one calendar date is the reading least likely to surprise anyone.
    expect(PaymentTerms::resolve(48, $fixed, Carbon::parse('2026-08-12 09:00:00'))->toDateTimeString())
        ->toBe('2026-09-01 23:59:59');
});

it('asks for no deadline when the trip sets none', function () {
    expect(PaymentTerms::resolve(null, null, now()))->toBeNull();
});

it('reads hours back the way a host would say them', function () {
    expect(PaymentTerms::describeHours(72))->toBe('3 days')
        ->and(PaymentTerms::describeHours(48))->toBe('2 days')
        ->and(PaymentTerms::describeHours(36))->toBe('36 hours')
        ->and(PaymentTerms::describeHours(1))->toBe('1 hour');
});

it('refuses a deadline further out than a year', function () {
    $due = PaymentTerms::resolve(999999, null, Carbon::parse('2026-08-12 09:00:00'));

    // Clamped rather than trusted: past a year it is a typo, not a policy.
    expect($due->diffInHours(Carbon::parse('2026-08-12 09:00:00')))->toBeLessThanOrEqual(PaymentTerms::MAX_HOURS);
});

it('gives a trip with no deposit no downpayment deadline, whatever the column says', function () {
    [, $trip] = deadlineTrip(['deposit_amount' => null, 'downpayment_due_hours' => 24]);

    expect(PaymentTerms::downpaymentDue($trip, now()))->toBeNull();
});

/*
 * ── Stamping them onto a booking ────────────────────────────────────────────────────────────
 */

it('stamps the deadline onto the booking when the seat is granted', function () {
    [$host, , $departure] = deadlineTrip([
        'deposit_amount' => 1000,
        'downpayment_due_hours' => 48,
        'full_payment_due_hours' => 336,
    ]);
    $service = app(ReservationService::class);
    $r = $service->reserve($departure, ['joiner_id' => User::factory()->create()->id]);

    expect($r->downpayment_due_at)->toBeNull();

    $service->confirm($r->fresh(), $host);
    $r->refresh();

    expect($r->downpayment_due_at->toDateString())->toBe(now()->addHours(48)->toDateString())
        ->and($r->full_payment_due_at->toDateString())->toBe(now()->addHours(336)->toDateString());
});

it('does not move a deadline a joiner was already told about', function () {
    [$host, $trip, $departure] = deadlineTrip(['full_payment_due_hours' => 48]);
    $service = app(ReservationService::class);
    $r = $service->reserve($departure, ['joiner_id' => User::factory()->create()->id]);
    $service->confirm($r->fresh(), $host);

    $promised = $r->fresh()->full_payment_due_at;

    // The host changes their mind about the policy a week later.
    $trip->update(['full_payment_due_hours' => 720]);

    // The promise already made stands. Deriving this on read instead of storing it would have
    // silently moved the date for everyone already confirmed.
    expect($r->fresh()->full_payment_due_at->toDateTimeString())->toBe($promised->toDateTimeString());
});

/*
 * ── Being late ──────────────────────────────────────────────────────────────────────────────
 */

it('flags a booking past its deadline without cancelling it', function () {
    [$host, , $departure] = deadlineTrip(['full_payment_due_hours' => 24]);
    $service = app(ReservationService::class);
    $r = $service->reserve($departure, ['joiner_id' => User::factory()->create()->id]);
    $service->confirm($r->fresh(), $host);

    $r->fresh()->update(['full_payment_due_at' => now()->subDay()]);

    expect($r->fresh()->paymentOverdue())->toBeTrue()
        // The seat is still theirs. The host granted it; a timer does not get to take it back.
        ->and($r->fresh()->status)->toBe('confirmed')
        ->and($departure->fresh()->heldCount())->toBe(1);
});

it('leaves an overdue booking alone when the expiry command runs', function () {
    [$host, , $departure] = deadlineTrip(['full_payment_due_hours' => 24]);
    $service = app(ReservationService::class);
    $r = $service->reserve($departure, ['joiner_id' => User::factory()->create()->id]);
    $service->confirm($r->fresh(), $host);
    $r->fresh()->update(['full_payment_due_at' => now()->subWeek()]);

    $service->expireStaleHolds();

    // expireStaleHolds only touches unconfirmed holds. A confirmed seat that is late on payment
    // must never fall through into it — that would be an auto-cancellation by the back door.
    expect($r->fresh()->status)->toBe('confirmed');
});

it('stops being overdue once the money is in', function () {
    [$host, , $departure] = deadlineTrip(['full_payment_due_hours' => 24]);
    $service = app(ReservationService::class);
    $r = $service->reserve($departure, ['joiner_id' => User::factory()->create()->id]);
    $service->confirm($r->fresh(), $host);
    $r->fresh()->update(['full_payment_due_at' => now()->subDay()]);
    expect($r->fresh()->paymentOverdue())->toBeTrue();

    $service->markFullyPaid($r->fresh(), $host);

    expect($r->fresh()->paymentOverdue())->toBeFalse();
});

it('moves to the balance deadline once the downpayment is verified', function () {
    [$host, , $departure] = deadlineTrip([
        'deposit_amount' => 1000,
        'downpayment_due_hours' => 48,
        'full_payment_due_hours' => 336,
    ]);
    $service = app(ReservationService::class);
    $r = $service->reserve($departure, ['joiner_id' => User::factory()->create()->id]);
    $service->confirm($r->fresh(), $host);

    // Before anything is paid, the near deadline is the one that counts.
    expect($r->fresh()->nextPaymentDue()->toDateString())->toBe(now()->addHours(48)->toDateString());

    $service->submitPayment($r->fresh(), ['reference_no' => 'GC-1']);
    $service->verifyPayment($r->fresh(), $host);

    // Deposit settled, so the only date left is the balance.
    expect($r->fresh()->nextPaymentDue()->toDateString())->toBe(now()->addHours(336)->toDateString());
});

/*
 * ── What the surfaces do with it ────────────────────────────────────────────────────────────
 */

it('shows no checkout to somebody whose seat has not been granted', function () {
    [, , $departure] = deadlineTrip();
    $r = app(ReservationService::class)->reserve($departure, [
        'joiner_id' => User::factory()->create(['role' => 'joiner'])->id,
    ]);

    $html = Livewire::test(Show::class, ['token' => $r->public_token])->html();

    expect($r->canSubmitPayment())->toBeFalse()
        ->and($html)->toContain('is reviewing your request')
        ->and($html)->not->toContain('dusk="submit-proof"');
});

it('shows the checkout the moment the seat is granted', function () {
    [$host, , $departure] = deadlineTrip();
    $service = app(ReservationService::class);
    $r = $service->reserve($departure, ['joiner_id' => User::factory()->create(['role' => 'joiner'])->id]);
    $service->confirm($r->fresh(), $host);

    $html = Livewire::test(Show::class, ['token' => $r->fresh()->public_token])->html();

    expect($r->fresh()->canSubmitPayment())->toBeTrue()
        ->and($html)->toContain('dusk="submit-proof"');
});

it('puts an overdue badge on the roster', function () {
    [$host, , $departure] = deadlineTrip(['full_payment_due_hours' => 24]);
    $service = app(ReservationService::class);
    $r = $service->reserve($departure, ['joiner_id' => User::factory()->create()->id]);
    $service->confirm($r->fresh(), $host);
    $r->fresh()->update(['full_payment_due_at' => now()->subDays(2)]);

    Livewire::actingAs($host)
        ->test(Roster::class, ['departure' => $departure])
        ->assertSee('Payment overdue');
});

it('offers the host a way to accept a proof that is separate from granting the seat', function () {
    [$host, , $departure] = deadlineTrip(['deposit_amount' => 1000]);
    $service = app(ReservationService::class);
    $r = $service->reserve($departure, ['joiner_id' => User::factory()->create()->id]);
    $service->confirm($r->fresh(), $host);
    $service->submitPayment($r->fresh(), ['reference_no' => 'GC-1', 'method' => 'GCash']);

    expect($r->fresh()->money()['paid'])->toBe(0.0);

    Livewire::actingAs($host)
        ->test(Roster::class, ['departure' => $departure])
        ->call('verifyPayment', $r->id);

    // The deposit lands, and only the deposit.
    expect($r->fresh()->money())->toMatchArray(['total' => 3000.0, 'paid' => 1000.0, 'due' => 2000.0]);
});
