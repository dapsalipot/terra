<?php

use App\Livewire\Host\Scanner;
use App\Livewire\Reservations\Pass;
use App\Livewire\Reservations\Show;
use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\ReservationService;
use Livewire\Livewire;

function passBooking(array $overrides = []): array
{
    $host = User::factory()->create(['role' => 'host', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Pulag hike', 'price' => 2000]);
    $departure = TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

    $reservation = Reservation::factory()->for($departure, 'departure')->create(array_merge([
        'joiner_id' => null, 'status' => 'confirmed', 'party_size' => 1,
        'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes', 'contact_email' => 'ada@example.com',
    ], $overrides));

    $participant = ReservationParticipant::factory()->for($reservation)->create([
        'position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02',
    ]);

    return [$host, $reservation, $participant, $departure];
}

it('issues a pass only once the host says the money is in', function () {
    [$host, $reservation, $participant] = passBooking();

    expect($participant->fresh()->pass_code)->toBeNull();

    app(ReservationService::class)->markFullyPaid($reservation, $host);

    expect($participant->fresh()->pass_code)->toStartWith('TRA-');
});

it('keeps a pass already saved to somebody phone when the booking is re-tagged', function () {
    [$host, $reservation, $participant] = passBooking();
    $service = app(ReservationService::class);

    $service->markFullyPaid($reservation, $host);
    $code = $participant->fresh()->pass_code;

    $service->markFullyPaid($reservation->fresh(), $host);

    expect($participant->fresh()->pass_code)->toBe($code);
});

it('withdraws the pass when the payment claim or the booking is withdrawn', function () {
    [$host, $reservation, $participant] = passBooking();
    $service = app(ReservationService::class);

    $service->markFullyPaid($reservation, $host);
    $service->unmarkFullyPaid($reservation->fresh());
    expect($participant->fresh()->pass_code)->toBeNull();

    $service->markFullyPaid($reservation->fresh(), $host);
    $service->cancel($reservation->fresh(), 'Cancelled by joiner');
    expect($participant->fresh()->pass_code)->toBeNull();
});

it('checks a traveller in from their code', function () {
    [$host, $reservation, $participant, $departure] = passBooking();
    app(ReservationService::class)->markFullyPaid($reservation, $host);
    $code = $participant->fresh()->pass_code;

    Livewire::actingAs($host)->test(Scanner::class, ['departure' => $departure])
        ->set('code', $code)
        ->call('scan')
        ->assertSet('lastOk', true)
        ->assertSee('Ada Reyes is in.');

    expect($participant->fresh()->checked_in_at)->not->toBeNull()
        ->and($participant->fresh()->checked_in_by)->toBe($host->id);
});

it('refuses a second scan, a foreign pass, and a code from nowhere', function () {
    [$host, $reservation, $participant, $departure] = passBooking();
    app(ReservationService::class)->markFullyPaid($reservation, $host);
    $code = $participant->fresh()->pass_code;

    $service = app(CheckInService::class);
    $service->checkIn($code, $host, $departure->id);

    // already in
    expect(fn () => $service->checkIn($code, $host, $departure->id))
        ->toThrow(RuntimeException::class, 'already checked in');

    // a code nobody issued
    expect(fn () => $service->checkIn('TRA-NOPE', $host, $departure->id))
        ->toThrow(RuntimeException::class, 'not one of ours');

    // somebody else's trip
    [$otherHost] = passBooking();
    expect(fn () => $service->checkIn($code, $otherHost, null))
        ->toThrow(RuntimeException::class, 'another host');
});

it('refuses a pass for a different departure of the same host', function () {
    [$host, $reservation, $participant] = passBooking();
    app(ReservationService::class)->markFullyPaid($reservation, $host);

    $otherDeparture = TripDeparture::factory()
        ->for(Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Another trip']))
        ->create(['capacity' => 5, 'starts_at' => now()->addWeeks(2)]);

    expect(fn () => app(CheckInService::class)->checkIn($participant->fresh()->pass_code, $host, $otherDeparture->id))
        ->toThrow(RuntimeException::class, 'not this departure');
});

it('shows the joiner a pass they can open and download', function () {
    [$host, $reservation, $participant] = passBooking();
    app(ReservationService::class)->markFullyPaid($reservation, $host);

    // offered from the booking page
    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->assertSeeHtml('dusk="open-pass"');

    Livewire::test(Pass::class, ['token' => $reservation->public_token])
        ->assertSee('Ada Reyes')
        ->assertSee($participant->fresh()->pass_code)
        ->assertSeeHtml('dusk="download-pass-'.$participant->id.'"');

    // and the QR itself renders as a PNG for whoever holds the token
    $this->get(route('passes.qr', ['participant' => $participant, 'token' => $reservation->public_token]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('keeps the pass page and its QR away from strangers', function () {
    [$host, $reservation, $participant] = passBooking();
    app(ReservationService::class)->markFullyPaid($reservation, $host);

    $this->get(route('passes.qr', $participant))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('passes.qr', $participant))->assertForbidden();
});

it('has no pass page before one is issued', function () {
    [, $reservation] = passBooking();

    $this->get(route('reservations.pass', ['token' => $reservation->public_token]))->assertNotFound();
});
