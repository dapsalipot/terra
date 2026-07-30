<?php

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('stores a trip cover on the public disk via the wizard', function () {
    Storage::fake('public');
    $host = User::factory()->create(['role' => 'host']);
    HostProfile::factory()->for($host)->create();
    $place = Place::factory()->create();

    Livewire::actingAs($host)
        ->test(\App\Livewire\Host\Trips\Create::class)
        ->call('addDestination', $place->id)
        ->set('title', 'Mount Rinjani Traverse')
        ->call('next')->call('next')
        ->set('price', 180)
        ->set('departures', [['starts_at' => now()->addWeeks(2)->toDateString(), 'ends_at' => '', 'capacity' => 10]])
        ->set('cover', UploadedFile::fake()->image('cover.jpg'))
        ->call('publish');

    $trip = Trip::first();
    expect($trip->cover_photo)->not->toBeNull();
    Storage::disk('public')->assertExists($trip->cover_photo);
    expect($trip->cover_url)->toContain('/storage/');
});

it('stores a payment proof on the private disk, not the public one', function () {
    Storage::fake('local');
    Storage::fake('public');

    $host = User::factory()->create(['role' => 'host']);
    HostProfile::factory()->for($host)->create();
    $trip = Trip::factory()->published()->create(['host_id' => $host->id]);
    $departure = TripDeparture::factory()->for($trip)->create();
    $joiner = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create([
        'trip_departure_id' => $departure->id, 'joiner_id' => $joiner->id, 'status' => 'pending',
    ]);

    Livewire::actingAs($joiner)
        ->test(\App\Livewire\Reservations\Show::class, ['token' => $reservation->public_token])
        ->set('reference_no', '9021')
        ->set('proof', UploadedFile::fake()->image('proof.jpg'))
        ->call('submit');

    $path = $reservation->fresh()->latestPayment->proof_image;
    expect($path)->not->toBeNull();
    Storage::disk('local')->assertExists($path);      // private
    Storage::disk('public')->assertMissing($path);    // not web-public
});

it('lets only the host view a payment proof', function () {
    Storage::fake('local');
    $host = User::factory()->create(['role' => 'host']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id]);
    $departure = TripDeparture::factory()->for($trip)->create();
    $reservation = Reservation::factory()->create(['trip_departure_id' => $departure->id]);
    $payment = $reservation->paymentSubmissions()->create([
        'reference_no' => 'x', 'submitted_at' => now(), 'proof_image' => 'proofs/x.jpg',
    ]);
    Storage::disk('local')->put('proofs/x.jpg', 'bytes');

    $this->actingAs(User::factory()->create(['role' => 'host']))
        ->get(route('host.proof', $payment))->assertForbidden();
    $this->actingAs($host)
        ->get(route('host.proof', $payment))->assertOk();
});
