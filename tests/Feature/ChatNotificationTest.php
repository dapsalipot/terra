<?php

use App\Livewire\Chat\Room;
use App\Mail\ChatActivityMail;
use App\Models\HostProfile;
use App\Models\Message;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ChatNotifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function chatDeparture(): array
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Pulag hike']);
    $departure = TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

    $ada = User::factory()->create(['role' => 'joiner', 'name' => 'Ada']);
    $ben = User::factory()->create(['role' => 'joiner', 'name' => 'Ben']);

    foreach ([$ada, $ben] as $j) {
        Reservation::factory()->for($departure, 'departure')->create([
            'joiner_id' => $j->id, 'status' => 'confirmed', 'party_size' => 1,
        ]);
    }

    return [$host, $departure, $ada, $ben];
}

it('tells the rest of the group, never the person who just spoke', function () {
    Mail::fake();
    [$host, $departure, $ada, $ben] = chatDeparture();

    Livewire::actingAs($ada)->test(Room::class, ['departure' => $departure])
        ->set('body', 'What time is the pickup?')
        ->call('send');

    // host + Ben, not Ada
    Mail::assertSent(ChatActivityMail::class, 2);
    Mail::assertSent(ChatActivityMail::class, fn ($m) => $m->hasTo($ben->email));
    Mail::assertSent(ChatActivityMail::class, fn ($m) => $m->hasTo($host->email));
    Mail::assertNotSent(ChatActivityMail::class, fn ($m) => $m->hasTo($ada->email));
});

it('sends one email a burst, not one an email per message', function () {
    Mail::fake();
    Cache::flush();
    [, $departure, $ada] = chatDeparture();

    // twenty messages the night before a trip is normal, and is exactly the case that turns a
    // useful notification into a filter rule
    foreach (range(1, 5) as $i) {
        Livewire::actingAs($ada)->test(Room::class, ['departure' => $departure])
            ->set('body', "Message {$i}")
            ->call('send');
    }

    Mail::assertSent(ChatActivityMail::class, 2);   // host + Ben, once each
});

it('speaks again once the quiet period has passed', function () {
    Mail::fake();
    Cache::flush();
    [, $departure, $ada] = chatDeparture();

    Livewire::actingAs($ada)->test(Room::class, ['departure' => $departure])
        ->set('body', 'First')->call('send');
    Mail::assertSent(ChatActivityMail::class, 2);

    $this->travel(2)->hours();

    Livewire::actingAs($ada)->test(Room::class, ['departure' => $departure])
        ->set('body', 'Much later')->call('send');

    Mail::assertSent(ChatActivityMail::class, 4);
});

it('never emails someone the chat would refuse entry to', function () {
    Mail::fake();
    Cache::flush();
    [, $departure, $ada] = chatDeparture();

    // an unconfirmed booking cannot read the room, so it must not be told about it
    $pending = User::factory()->create(['role' => 'joiner']);
    Reservation::factory()->for($departure, 'departure')->create([
        'joiner_id' => $pending->id, 'status' => 'pending', 'party_size' => 1,
    ]);

    app(ChatNotifier::class)->announce(Message::create([
        'trip_departure_id' => $departure->id, 'user_id' => $ada->id, 'body' => 'Hello',
    ]));

    Mail::assertNotSent(ChatActivityMail::class, fn ($m) => $m->hasTo($pending->email));
});

it('carries no message text, only that the conversation moved', function () {
    Mail::fake();
    Cache::flush();
    [, $departure, $ada] = chatDeparture();

    Livewire::actingAs($ada)->test(Room::class, ['departure' => $departure])
        ->set('body', 'My bank details are 1234')->call('send');

    Mail::assertSent(ChatActivityMail::class, function ($mail) {
        return ! str_contains($mail->render(), 'My bank details are 1234');
    });
});
