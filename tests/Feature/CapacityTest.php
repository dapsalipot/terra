<?php

use App\Exceptions\TripFullException;
use App\Models\Reservation;
use App\Models\TripDeparture;
use App\Services\ReservationService;

beforeEach(function () {
    $this->svc = app(ReservationService::class);
});

it('never oversells a departure', function () {
    $departure = TripDeparture::factory()->create(['capacity' => 4]);

    $this->svc->reserve($departure, ['party_size' => 3]);          // 3 held
    $this->svc->reserve($departure, ['party_size' => 1]);          // 4 held (full)

    expect($departure->fresh()->heldCount())->toBe(4)
        ->and($departure->fresh()->spotsLeft())->toBe(0)
        ->and($departure->fresh()->status)->toBe('full');

    // one more seat must be refused
    $this->svc->reserve($departure, ['party_size' => 1]);
})->throws(TripFullException::class);

it('a party larger than remaining capacity is refused atomically', function () {
    $departure = TripDeparture::factory()->create(['capacity' => 5]);
    $this->svc->reserve($departure, ['party_size' => 4]);          // 1 left

    expect(fn () => $this->svc->reserve($departure, ['party_size' => 2]))
        ->toThrow(TripFullException::class);

    // the refused reservation left no trace
    expect(Reservation::count())->toBe(1)
        ->and($departure->fresh()->heldCount())->toBe(4);
});

it('cancelled reservations free their seats for new bookings', function () {
    $departure = TripDeparture::factory()->create(['capacity' => 2]);
    $a = $this->svc->reserve($departure, ['party_size' => 2]);     // full
    expect(fn () => $this->svc->reserve($departure, ['party_size' => 1]))->toThrow(TripFullException::class);

    $this->svc->cancel($a);
    $this->svc->reserve($departure, ['party_size' => 2]);          // fits again
    expect($departure->fresh()->heldCount())->toBe(2);
});

it('offline manual participants also consume capacity', function () {
    $departure = TripDeparture::factory()->create(['capacity' => 3]);
    $this->svc->addManualParticipant($departure, ['guest_name' => 'Ramil Cruz', 'party_size' => 2]);

    $r = $departure->reservations()->first();
    expect($r->isOffline())->toBeTrue()
        ->and($r->status)->toBe('confirmed')
        ->and($departure->fresh()->heldCount())->toBe(2)
        ->and($departure->fresh()->spotsLeft())->toBe(1);
});
