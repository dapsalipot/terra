<?php

use App\Livewire\Host\Roster;
use App\Livewire\Reservations\Register;
use App\Livewire\Reservations\Show;
use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
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
        ->call('openBooking', $reservation->id)
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
        ->call('openBooking', $reservation->id)
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

/*
 * ── The join form ────────────────────────────────────────────────────────────────────────
 *
 * A rollback undoes rows, not files. However a booking fails, the ID photos saved for it have
 * to be taken off the disk again, or they stay there belonging to nobody.
 */

/** A departure of a published trip that asks for ID. */
function idDeparture(): TripDeparture
{
    $trip = Trip::factory()->published()->create(['requires_id' => true]);

    return TripDeparture::factory()->for($trip)->create(['capacity' => 10]);
}

/** The join form filled in for `$seats` travellers, each with a photo. */
function idJoinForm(Trip $trip, int $seats = 1): Testable
{
    $form = Livewire::test(Register::class, ['trip' => $trip])
        ->set('partySize', $seats)
        ->set('contact', [
            'first_name' => 'Ada', 'middle_name' => '', 'last_name' => 'Reyes',
            'email' => 'ada@example.com', 'phone' => '09171234567',
        ])
        ->set('createAccount', false);

    foreach (range(0, $seats - 1) as $seat) {
        $form->set("participants.{$seat}.first_name", 'Guest')
            ->set("participants.{$seat}.last_name", 'Number'.$seat)
            ->set("participants.{$seat}.email", "guest{$seat}@example.com")
            ->set("participants.{$seat}.emergency_contact_name", 'Rosa Reyes')
            ->set("participants.{$seat}.emergency_contact_phone", '09171112222')
            ->set("ids.{$seat}", UploadedFile::fake()->image("licence-{$seat}.jpg"));
    }

    return $form;
}

it('keeps no ID photo from a refused booking, and can still book with the same upload', function () {
    Storage::fake('local');
    $departure = idDeparture();
    $form = idJoinForm($departure->trip);
    $rival = Reservation::factory()->for($departure, 'departure')->create(['party_size' => 10, 'status' => 'confirmed']);

    $form->call('submit')->assertNoRedirect()->assertSet('error', 'This departure is full.');

    expect(Storage::disk('local')->allFiles('ids'))->toBeEmpty();

    // The form still holds the upload, so a freed seat does not send anyone back for the photo.
    $rival->delete();
    $form->call('submit')->assertHasNoErrors()->assertRedirect();

    $stored = Storage::disk('local')->allFiles('ids');

    expect($stored)->toHaveCount(1)
        ->and(Reservation::sole()->participants->sole()->id_image)->toBe($stored[0]);
});

it('keeps no ID photo when another submit got the booking in first', function () {
    Storage::fake('local');
    $departure = idDeparture();
    $joiner = User::factory()->create(['role' => 'joiner']);
    $this->actingAs($joiner);
    $form = idJoinForm($departure->trip);
    $planted = false;

    // As in RegisterComponentTest: the rival lands just after the form has looked and found nothing.
    DB::listen(function ($query) use ($departure, $joiner, &$planted) {
        if ($planted || ! str_starts_with($query->sql, 'select') || ! str_contains($query->sql, '"joiner_id" = ?')) {
            return;
        }

        $planted = true;
        Reservation::factory()->for($departure, 'departure')->create(['joiner_id' => $joiner->id, 'status' => 'pending']);
    });

    $form->call('submit')->assertRedirect();

    expect($planted)->toBeTrue()
        ->and(Reservation::count())->toBe(1)
        ->and(Storage::disk('local')->allFiles('ids'))->toBeEmpty();
});

it('keeps no ID photo when writing the booking fails', function () {
    Storage::fake('local');
    $form = idJoinForm(idDeparture()->trip);

    // The database giving out part-way, by which time the photo is already on the disk.
    ReservationParticipant::creating(fn () => throw new RuntimeException('database went away'));

    expect(fn () => $form->call('submit'))->toThrow(RuntimeException::class, 'database went away');
    expect(Storage::disk('local')->allFiles('ids'))->toBeEmpty();
});

it('keeps no ID photo when a later one cannot be saved', function () {
    $disk = Storage::fake('local');
    $form = idJoinForm(idDeparture()->trip, seats: 2);
    $writes = 0;

    // The real disk, except that it gives out on the second photo.
    $failing = Mockery::mock($disk)->makePartial();
    $failing->shouldReceive('put')->andReturnUsing(function (...$write) use ($disk, &$writes) {
        if (++$writes === 2) {
            throw new RuntimeException('disk full');
        }

        return $disk->put(...$write);
    });
    Storage::set('local', $failing);

    expect(fn () => $form->call('submit'))->toThrow(RuntimeException::class, 'disk full');

    expect($writes)->toBe(2)
        ->and($disk->allFiles('ids'))->toBeEmpty();
});

it('saves a photo only for a traveller who gets a seat', function () {
    Storage::fake('local');
    $departure = idDeparture();
    $form = idJoinForm($departure->trip, seats: 3);
    // Seats go while the form is open. The refusal lowers the seat count and leaves all three
    // travellers on the form.
    Reservation::factory()->for($departure, 'departure')->create(['party_size' => 9, 'status' => 'confirmed']);

    $form->call('submit')->assertNoRedirect()->assertSet('partySize', 1);
    $form->call('submit')->assertRedirect();

    $kept = Reservation::where('contact_email', 'ada@example.com')->sole()->participants->pluck('id_image')->all();

    expect(Storage::disk('local')->allFiles('ids'))->toHaveCount(1)->toBe($kept);
});
