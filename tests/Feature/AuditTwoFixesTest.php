<?php

use App\Livewire\Host\Scanner;
use App\Livewire\Reservations\Register;
use App\Mail\PaymentSubmittedMail;
use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\ModerationService;
use App\Services\ReservationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function auditTrip(bool $requiresId = false): TripDeparture
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP', 'payment_methods' => [['label' => 'GCash', 'detail' => '0917']]]);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'price' => 2000, 'requires_id' => $requiresId]);

    return TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);
}

it('refuses a registration with no ID when the trip requires one', function () {
    $departure = auditTrip(requiresId: true);

    Livewire::test(Register::class, ['trip' => $departure->trip])
        ->set('contact.first_name', 'Ada')->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')->set('contact.phone', '09171234567')
        ->set('participants.0.birthday', '1998-04-02')
        ->call('submit')
        ->assertHasErrors('ids.0');

    expect(Reservation::count())->toBe(0);
});

it('accepts one and files it against the traveller', function () {
    Storage::fake('local');
    $departure = auditTrip(requiresId: true);

    Livewire::test(Register::class, ['trip' => $departure->trip])
        ->set('contact.first_name', 'Ada')->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')->set('contact.phone', '09171234567')
        ->set('participants.0.birthday', '1998-04-02')
        ->set('ids.0', UploadedFile::fake()->image('licence.jpg'))
        ->call('submit')
        ->assertHasNoErrors();

    $participant = Reservation::firstOrFail()->participants->first();

    expect($participant->id_image)->toStartWith('ids/')
        ->and(Storage::disk('local')->exists($participant->id_image))->toBeTrue();
});

it('asks for nothing extra on a trip that does not require an ID', function () {
    $departure = auditTrip(requiresId: false);

    Livewire::test(Register::class, ['trip' => $departure->trip])
        ->set('contact.first_name', 'Ada')->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')->set('contact.phone', '09171234567')
        ->set('participants.0.birthday', '1998-04-02')
        ->call('submit')
        ->assertHasNoErrors();

    expect(Reservation::count())->toBe(1);
});

it('gives a walk-in a traveller record so they can hold a pass', function () {
    $departure = auditTrip();

    $reservation = app(ReservationService::class)->addManualParticipant($departure, [
        'guest_name' => 'Ben Tan', 'party_size' => 2,
    ]);

    expect($reservation->participants)->toHaveCount(2)
        ->and($reservation->participants->first()->fullName())->toBe('Ben Tan');

    // and that record can carry a pass, which was impossible before
    app(ReservationService::class)->markFullyPaid($reservation->fresh(), $departure->trip->host);
    expect($reservation->fresh()->participants->first()->pass_code)->toStartWith('TRA-');
});

it('locks a suspended host out of the host area but not their joiners out of theirs', function () {
    $departure = auditTrip();
    $host = $departure->trip->host;
    $reservation = Reservation::factory()->for($departure, 'departure')->create([
        'joiner_id' => null, 'status' => 'confirmed', 'contact_email' => 'ada@example.com',
    ]);

    app(ModerationService::class)->suspend($host, 'Under review');

    $this->actingAs($host)->get(route('host.trips.index'))->assertForbidden();

    // The joiner's own page is token-based and untouched by the suspension.
    $this->get(route('reservations.show', ['token' => $reservation->public_token]))->assertOk();
});

it('lets a host undo a mis-scan', function () {
    $departure = auditTrip();
    $host = $departure->trip->host;
    $reservation = Reservation::factory()->for($departure, 'departure')->create(['joiner_id' => null, 'status' => 'confirmed']);
    $p = ReservationParticipant::factory()->for($reservation)->create([
        'position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02',
    ]);

    app(ReservationService::class)->markFullyPaid($reservation, $host);
    $code = $p->fresh()->pass_code;

    $service = app(CheckInService::class);
    $service->checkIn($code, $host, $departure->id);

    Livewire::actingAs($host)->test(Scanner::class, ['departure' => $departure])
        ->assertSeeHtml('dusk="undo-'.$p->id.'"')
        ->call('undo', $p->id);

    expect($p->fresh()->checked_in_at)->toBeNull();

    // and the correct scan now works, which the "already checked in" guard had blocked
    $service->checkIn($code, $host, $departure->id);
    expect($p->fresh()->checked_in_at)->not->toBeNull();
});

it('tells the host when a payment arrives', function () {
    Mail::fake();
    $departure = auditTrip();
    $reservation = Reservation::factory()->for($departure, 'departure')->create([
        'joiner_id' => null, 'status' => 'pending', 'contact_email' => 'ada@example.com',
    ]);

    $reservation->update(['status' => 'confirmed', 'confirmed_at' => now()]);
    app(ReservationService::class)->submitPayment($reservation->fresh(), ['reference_no' => '9021', 'method' => 'GCash']);

    Mail::assertSent(PaymentSubmittedMail::class, fn ($m) => $m->hasTo($departure->trip->host->email));
});
