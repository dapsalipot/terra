<?php

use App\Livewire\Host\Roster;
use App\Livewire\Reservations\Show;
use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function paidBooking(): array
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'price' => 2000]);
    $departure = TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

    $reservation = Reservation::factory()->for($departure, 'departure')->create([
        'joiner_id' => null, 'status' => 'confirmed', 'party_size' => 1,
        'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes', 'contact_email' => 'ada@example.com',
    ]);

    app(ReservationService::class)->markFullyPaid($reservation, $host);

    return [$host, $reservation->fresh(), $departure];
}

it('captures what was paid at the moment of cancellation', function () {
    Mail::fake();
    [, $reservation] = paidBooking();

    expect($reservation->money()['paid'])->toBe(2000.0);

    app(ReservationService::class)->cancel($reservation, 'Cancelled by your host');
    $reservation->refresh();

    // money() can no longer answer this — a cancelled booking reports paid: 0 — which is exactly
    // why the amount is snapshotted rather than derived.
    expect($reservation->money()['paid'])->toBe(0.0)
        ->and((float) $reservation->refund_due)->toBe(2000.0)
        ->and($reservation->owesRefund())->toBeTrue();
});

it('records nothing to refund when nothing was paid', function () {
    Mail::fake();
    [, $reservation] = paidBooking();
    app(ReservationService::class)->unmarkFullyPaid($reservation);

    app(ReservationService::class)->cancel($reservation->fresh(), 'Hold expired');

    expect($reservation->fresh()->refund_due)->toBeNull()
        ->and($reservation->fresh()->owesRefund())->toBeFalse();
});

it('lets the host settle it and stops asking once they have', function () {
    Mail::fake();
    [$host, $reservation, $departure] = paidBooking();
    app(ReservationService::class)->cancel($reservation, 'Cancelled by your host');

    Livewire::actingAs($host)->test(Roster::class, ['departure' => $departure])
        ->assertSeeHtml('dusk="refund-'.$reservation->id.'"')
        ->assertSee('Refund owed')
        ->assertSeeHtml('dusk="mark-refunded-'.$reservation->id.'"')
        ->call('markRefunded', $reservation->id);

    $reservation->refresh();

    expect($reservation->refunded_at)->not->toBeNull()
        ->and($reservation->refunded_by)->toBe($host->id)
        ->and($reservation->owesRefund())->toBeFalse();

    Livewire::actingAs($host)->test(Roster::class, ['departure' => $departure])
        ->assertSee('Refunded')
        ->assertDontSeeHtml('dusk="mark-refunded-'.$reservation->id.'"');
});

it('tells the joiner money is coming back, and when it has', function () {
    Mail::fake();
    [$host, $reservation] = paidBooking();
    app(ReservationService::class)->cancel($reservation, 'Cancelled by your host');

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->assertSeeHtml('dusk="refund-notice"')
        ->assertSee('is owed back to you');

    app(ReservationService::class)->markRefunded($reservation->fresh(), $host);

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->assertSee('has refunded');
});

it('captures a refund for everyone when a whole departure is called off', function () {
    Mail::fake();
    [$host, $reservation, $departure] = paidBooking();

    app(ReservationService::class)->cancelDeparture($departure, 'Typhoon signal 3');

    expect((float) $reservation->fresh()->refund_due)->toBe(2000.0);
});
