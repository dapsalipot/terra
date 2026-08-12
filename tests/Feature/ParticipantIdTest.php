<?php

use App\Livewire\Host\Roster;
use App\Livewire\Reservations\Show;
use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function idBooking(bool $requiresId = true): array
{
    $host = User::factory()->create(['role' => 'host', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'requires_id' => $requiresId]);
    $departure = TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

    $reservation = Reservation::factory()->for($departure, 'departure')->create([
        'joiner_id' => null, 'status' => 'confirmed', 'party_size' => 1,
        'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes', 'contact_email' => 'ada@example.com',
    ]);

    $participant = ReservationParticipant::factory()->for($reservation)->create([
        'position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02',
    ]);

    return [$host, $reservation, $participant];
}

it('asks for an ID only on trips that need one', function () {
    [, $withId] = idBooking(true);
    [, $without] = idBooking(false);

    Livewire::test(Show::class, ['token' => $withId->public_token])
        ->assertSee('Photo ID');

    Livewire::test(Show::class, ['token' => $without->public_token])
        ->assertDontSee('Photo ID');
});

it('flags the travellers who still owe one', function () {
    [$host, $reservation, $participant] = idBooking();

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->assertSeeHtml('dusk="id-missing-'.$participant->id.'"');

    Livewire::actingAs($host)->test(Roster::class, ['departure' => $reservation->departure])
        ->assertSeeHtml('dusk="roster-id-missing-'.$participant->id.'"');
});

it('stores an uploaded ID on the private disk and links it for the host', function () {
    Storage::fake('local');
    [$host, $reservation, $participant] = idBooking();

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('editParticipant', $participant->id)
        ->set('edit_id', UploadedFile::fake()->image('licence.jpg'))
        ->call('updateParticipant')
        ->assertHasNoErrors();

    $participant->refresh();

    expect($participant->id_image)->toStartWith('ids/')
        ->and($participant->id_uploaded_at)->not->toBeNull()
        ->and(Storage::disk('local')->exists($participant->id_image))->toBeTrue();

    Livewire::actingAs($host)->test(Roster::class, ['departure' => $reservation->departure])
        ->assertSeeHtml('dusk="roster-id-'.$participant->id.'"');
});

it('serves an ID to the host and to the booking token, and to nobody else', function () {
    Storage::fake('local');
    [$host, $reservation, $participant] = idBooking();
    $participant->update(['id_image' => 'ids/licence.jpg', 'id_uploaded_at' => now()]);
    Storage::disk('local')->put('ids/licence.jpg', 'bytes');

    $this->actingAs($host)->get(route('participants.id', $participant))->assertOk();
    $this->get(route('participants.id', ['participant' => $participant, 'token' => $reservation->public_token]))->assertOk();

    // A signed-in stranger, and an anonymous request with no token, both get nothing.
    $this->actingAs(User::factory()->create())->get(route('participants.id', $participant))->assertForbidden();
    auth()->logout();
    $this->get(route('participants.id', $participant))->assertForbidden();
});

it('replaces rather than orphans the previous ID', function () {
    Storage::fake('local');
    [, $reservation, $participant] = idBooking();
    $participant->update(['id_image' => 'ids/old.jpg']);
    Storage::disk('local')->put('ids/old.jpg', 'old');

    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('editParticipant', $participant->id)
        ->set('edit_id', UploadedFile::fake()->image('new.jpg'))
        ->call('updateParticipant');

    expect(Storage::disk('local')->exists('ids/old.jpg'))->toBeFalse()
        ->and($participant->fresh()->id_image)->not->toBe('ids/old.jpg');
});
