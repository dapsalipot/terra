<?php

use App\Exceptions\InvalidTransitionException;
use App\Models\Reservation;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ReservationService;

beforeEach(function () {
    $this->svc = app(ReservationService::class);
    $this->departure = TripDeparture::factory()->create(['capacity' => 14]);
    $this->joiner = User::factory()->create(['role' => 'joiner']);
    $this->host = User::factory()->create(['role' => 'host']);
});

it('walks the happy path: reserve -> submit -> confirm', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id, 'party_size' => 2]);
    expect($r->status)->toBe('pending')
        ->and($r->expires_at)->not->toBeNull();

    $this->svc->submitPayment($r, ['method' => 'Bank transfer', 'reference_no' => '9021']);
    expect($r->fresh()->status)->toBe('payment_submitted');

    $this->svc->confirm($r->fresh(), $this->host);
    expect($r->fresh()->status)->toBe('confirmed')
        ->and($r->fresh()->expires_at)->toBeNull();
});

it('reject sends a submission back to awaiting_payment and keeps the seat', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    $this->svc->submitPayment($r, ['reference_no' => 'x']);
    $this->svc->reject($r->fresh(), $this->host, 'Reference not found');

    $r->refresh();
    expect($r->status)->toBe('awaiting_payment')
        ->and($r->latestPayment->rejected_reason)->toBe('Reference not found')
        ->and($this->departure->fresh()->heldCount())->toBe(1); // still holds the seat
});

it('cannot confirm a reservation that has not submitted payment', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id]);
    $this->svc->confirm($r, $this->host);
})->throws(InvalidTransitionException::class);

it('cancelling releases the seat', function () {
    $r = $this->svc->reserve($this->departure, ['joiner_id' => $this->joiner->id, 'party_size' => 3]);
    expect($this->departure->fresh()->heldCount())->toBe(3);

    $this->svc->cancel($r, 'Change of plans');
    expect($r->fresh()->status)->toBe('cancelled')
        ->and($this->departure->fresh()->heldCount())->toBe(0);
});
