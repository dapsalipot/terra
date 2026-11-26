<?php

use App\Livewire\Reservations\Register;
use App\Mail\MagicLinkMail;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->trip = Trip::factory()->create(['status' => 'published', 'price' => 3500]);
    $this->departure = TripDeparture::factory()->create([
        'trip_id' => $this->trip->id, 'capacity' => 10, 'status' => 'open',
    ]);
});

it('starts with one seat, one participant and the booker travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->assertSet('partySize', 1)
        ->assertSet('bookerIsTraveling', true)
        ->assertCount('participants', 1);
});

it('grows and shrinks the participant list with the seat count', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', 3)
        ->assertCount('participants', 3)
        ->set('partySize', 2)
        ->assertCount('participants', 2);
});

it('mirrors the booker name into seat one while they are travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->assertSet('participants.0.first_name', 'Ada')
        ->assertSet('participants.0.last_name', 'Reyes');
});

it('clears an untouched mirrored seat when the booker stops travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('bookerIsTraveling', false)
        ->assertSet('participants.0.first_name', '')
        ->assertSet('participants.0.last_name', '');
});

it('preserves a seat the booker edited when they stop travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('participants.0.first_name', 'Ben')   // deliberately someone else
        ->set('bookerIsTraveling', false)
        ->assertSet('participants.0.first_name', 'Ben');
});

it('requires the booker contact block', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertHasErrors([
            'contact.first_name' => 'required',
            'contact.last_name' => 'required',
            'contact.email' => 'required',
            'contact.phone' => 'required',
        ]);
});

/*
 * Every seat needs a name.
 *
 * The form used to accept a blank seat and tell the booker they could "add them later", which
 * handed the host a roster of anonymous rows and no way to chase the gap. A seat is a person now.
 */
it('requires a name on every traveller seat', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('partySize', 2)   // seat 1 is left untouched

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->set('participants.1.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.1.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertHasErrors([
            'participants.1.first_name' => 'required',
            'participants.1.last_name' => 'required',
        ]);

    expect(Reservation::count())->toBe(0);
});

/*
 * The seat has to be nameable in the message, or the booker gets "The participants.1.first name
 * field is required" and has to count cards to work out which one.
 */
it('names the seat in the message so the booker knows which card to fix', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('partySize', 2)

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->set('participants.1.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.1.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertSee('Seat 2 needs a first name.');
});

/*
 * Every seat needs a reachable address too, so the host can send joining instructions to the
 * person travelling rather than relying on the booker to forward them.
 */
it('requires an email on every traveller seat', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('partySize', 2)
        ->set('participants.1.first_name', 'Ben')
        ->set('participants.1.last_name', 'Cruz')

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->set('participants.1.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.1.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertHasErrors(['participants.1.email' => 'required'])
        ->assertSee('Seat 2 needs an email address.');

    expect(Reservation::count())->toBe(0);
});

it('rejects a traveller email that is not an email', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('partySize', 2)
        ->set('participants.1.first_name', 'Ben')
        ->set('participants.1.last_name', 'Cruz')
        ->set('participants.1.email', 'ben-at-example')

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->set('participants.1.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.1.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertHasErrors(['participants.1.email' => 'email']);
});

/*
 * The whole point of the button: a booker rarely knows five friends' addresses, and without a
 * one-tap answer a required field here would just be a wall.
 */
it('copies the booker email onto a seat on request', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.email', 'ada@example.com')
        ->set('partySize', 2)
        ->call('useBookerEmail', 1)
        ->assertSet('participants.1.email', 'ada@example.com');
});

/*
 * Several seats sharing one address is the expected outcome of that button, not an error —
 * a parent booking for three children has one inbox between them.
 */
it('accepts the same email on more than one seat', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('partySize', 3)
        ->set('participants.1.first_name', 'Ben')->set('participants.1.last_name', 'Cruz')
        ->set('participants.2.first_name', 'Cee')->set('participants.2.last_name', 'Cruz')
        ->call('useBookerEmail', 1)
        ->call('useBookerEmail', 2)
        ->set('createAccount', false)

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->set('participants.1.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.1.emergency_contact_phone', '09171112222')
        ->set('participants.2.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.2.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect(Reservation::first()->participants->pluck('email')->all())
        ->toBe(['ada@example.com', 'ada@example.com', 'ada@example.com']);
});

/*
 * The booker's own seat is the contact block, so asking for its email again would be a second
 * box for an address already given — and a second required field they cannot see the point of.
 * It is filled from the contact on the way to the database so the roster is still complete.
 */
it('fills the booker seat email from their contact details without asking twice', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('createAccount', false)

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertHasNoErrors('participants.0.email')
        ->assertRedirect();

    expect(Reservation::first()->participants->first()->email)->toBe('ada@example.com');
});

/*
 * A booker who deliberately set a different address on their own seat keeps it — the fallback
 * fills a blank, it does not overwrite an answer.
 */
it('keeps a different email the booker chose for their own seat', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('participants.0.email', 'ada.personal@example.com')
        ->set('createAccount', false)

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertRedirect();

    expect(Reservation::first()->participants->first()->email)->toBe('ada.personal@example.com');
});

/*
 * The trip panel's gallery.
 *
 * cover_url is not a plain accessor — it falls back to a stock photo keyed to the place category
 * when the host has uploaded no cover. So the gallery cannot simply be "cover, then photos": a
 * host who uploaded three real photos but never set a cover would lead with a stock image of
 * somewhere they have never been.
 */
it('leads the gallery with the host cover then their other photos', function () {
    $this->trip->update(['cover_photo' => 'covers/real-cover.jpg']);
    $this->trip->photos()->createMany([
        ['path' => 'trips/two.jpg', 'position' => 1],
        ['path' => 'trips/three.jpg', 'position' => 2],
    ]);

    $gallery = Livewire::test(Register::class, ['trip' => $this->trip])->viewData('gallery');

    expect($gallery)->toHaveCount(3)
        ->and($gallery[0]['url'])->toContain('real-cover.jpg')
        ->and($gallery[1]['url'])->toContain('two.jpg')
        ->and($gallery[2]['url'])->toContain('three.jpg');
});

it('never leads with a stock photo when the host has real ones', function () {
    $this->trip->update(['cover_photo' => null]);
    $this->trip->photos()->create(['path' => 'trips/real.jpg', 'position' => 1]);

    $gallery = Livewire::test(Register::class, ['trip' => $this->trip])->viewData('gallery');

    expect($gallery)->toHaveCount(1)
        ->and($gallery[0]['url'])->toContain('real.jpg');
});

it('falls back to the stock photo when the trip has none of its own', function () {
    $this->trip->update(['cover_photo' => null]);

    $gallery = Livewire::test(Register::class, ['trip' => $this->trip])->viewData('gallery');

    expect($gallery)->toHaveCount(1)
        ->and($gallery[0]['url'])->not->toBeEmpty();
});

it('shows carousel controls only when there is more than one photo', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])->assertDontSee('Next image');

    $this->trip->photos()->createMany([
        ['path' => 'trips/a.jpg', 'position' => 1],
        ['path' => 'trips/b.jpg', 'position' => 2],
    ]);

    // fresh(), because the instance above still carries the empty photos relation it loaded
    // during the first render. A real request resolves the model from the route each time.
    Livewire::test(Register::class, ['trip' => $this->trip->fresh()])->assertSee('Next image');
});

/*
 * One empty box, one message.
 *
 * Seat 1 is the contact block while the booker is travelling. If both rule sets fired, clearing
 * the contact name would show "first name is required" and "Seat 1 needs a first name" together,
 * and the booker would go looking for a second field that does not exist on screen.
 */
it('does not report the booker seat and the contact block as two separate problems', function () {
    $component = Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->call('submit');

    $component->assertHasErrors('contact.first_name')
        ->assertHasNoErrors('participants.0.first_name')
        ->assertHasNoErrors('participants.0.last_name');
});

/*
 * A booker who is not travelling has no mirrored seat, so seat 1 is a stranger they must name.
 * Without this the "I'm booking for other people" path could submit an entirely nameless roster.
 */
it('requires seat one when the booker is not travelling', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('bookerIsTraveling', false)

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertHasErrors(['participants.0.first_name' => 'required']);
});

it('registers a guest and sends them to their booking', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('participants.0.birthday', '1998-04-02')
        ->set('participants.0.gender', 'female')
        ->set('createAccount', false)

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertRedirect();

    $reservation = Reservation::first();

    expect($reservation->joiner_id)->toBeNull()
        ->and($reservation->contact_email)->toBe('ada@example.com')
        ->and($reservation->participants)->toHaveCount(1);
});

it('mails the sign-in link and keeps no copy where the person booking could reach it', function () {
    Mail::fake();
    // Someone else's account: the link mailed here signs in to it, and the person booking only typed the address.
    User::factory()->create(['email' => 'ada@example.com']);

    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('participants.0.birthday', '1998-04-02')
        ->set('createAccount', true)

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertRedirect();

    Mail::assertSent(MagicLinkMail::class, 1);
    expect(session('registration_outcome'))->toBe('email_taken')
        ->and(json_encode(session()->all(), JSON_UNESCAPED_SLASHES))->not->toContain('/magic/');
});

it('normalises a +63 mobile number to the 09 form', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '+63 917 123 4567')
        ->set('participants.0.birthday', '1998-04-02')
        ->set('createAccount', false)

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->call('submit');

    expect(Reservation::first()->contact_phone)->toBe('09171234567');
});

it('turns a host away', function () {
    $host = User::factory()->create(['role' => 'host']);

    Livewire::actingAs($host)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertRedirect(route('trips.show', $this->trip));
});

it('pre-fills a signed-in joiner from their profile', function () {
    $joiner = User::factory()->create([
        'role' => 'joiner', 'first_name' => 'Ada', 'last_name' => 'Reyes',
        'email' => 'ada@example.com', 'phone' => '09171234567', 'birthday' => '1998-04-02',
    ]);

    Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertSet('contact.first_name', 'Ada')
        ->assertSet('contact.email', 'ada@example.com')
        ->assertSet('participants.0.birthday', '1998-04-02');
});

it('refuses a departure that has already left', function () {
    $this->departure->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subDay()]);

    Livewire::test(Register::class, ['trip' => $this->trip])
        ->assertSet('closed', true)
        ->assertSee('no longer taking registrations');
});

it('refuses a cancelled departure', function () {
    $this->departure->update(['status' => 'cancelled']);

    Livewire::test(Register::class, ['trip' => $this->trip])
        ->assertSet('closed', true);
});

it('sends a joiner who already booked to their existing booking', function () {
    $joiner = User::factory()->create(['role' => 'joiner']);
    $existing = Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id,
        'joiner_id' => $joiner->id,
        'status' => 'pending',
    ]);

    Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertRedirect(route('reservations.show', ['token' => $existing->public_token]));
});

/*
 * mount() only knows what was true when the page opened. Two tabs can both be open before either
 * books, so the rule has to be asked again when the booking is made.
 */
it('sends a second tab to the booking the first one made', function () {
    Storage::fake('local');
    $this->trip->update(['requires_id' => true]);
    $joiner = User::factory()->create([
        'role' => 'joiner', 'first_name' => 'Ada', 'last_name' => 'Reyes', 'phone' => '09171234567',
    ]);

    [$first, $second] = array_map(fn () => Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->set('ids.0', UploadedFile::fake()->image('licence.jpg')), [1, 2]);

    $first->call('submit')->assertRedirect();
    $booking = Reservation::sole();

    $second->call('submit')
        ->assertRedirect(route('reservations.show', ['token' => $booking->public_token]));

    expect(Reservation::count())->toBe(1)
        // Nothing more was counted against the booking limit, and no second copy of the ID was kept.
        ->and(RateLimiter::attempts('booking:email:'.sha1(strtolower($joiner->email))))->toBe(1)
        ->and(Storage::disk('local')->allFiles('ids'))->toHaveCount(1);
});

/*
 * Two submits sent together can both get past the form's own look. DB::listen fires after a query
 * has run and before its caller reads the result, so a rival planted here is one the form has
 * just failed to see.
 */
it('opens the other booking when it appears while this one is being sent', function () {
    $joiner = User::factory()->create([
        'role' => 'joiner', 'first_name' => 'Ada', 'last_name' => 'Reyes', 'phone' => '09171234567',
    ]);

    $form = Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222');

    $rival = null;
    $planted = false;

    DB::listen(function ($query) use ($joiner, &$rival, &$planted) {
        if ($planted || ! str_starts_with($query->sql, 'select') || ! str_contains($query->sql, '"joiner_id" = ?')) {
            return;
        }

        $planted = true;
        $rival = Reservation::factory()->create([
            'trip_departure_id' => $this->departure->id, 'joiner_id' => $joiner->id, 'status' => 'pending',
        ]);
    });

    $form->call('submit');

    expect($planted)->toBeTrue();

    $form->assertRedirect(route('reservations.show', ['token' => $rival->public_token]));

    expect(Reservation::count())->toBe(1)
        // The place this attempt took in the booking limit is handed back.
        ->and(RateLimiter::attempts('booking:email:'.sha1(strtolower($joiner->email))))->toBe(0);
});

it('does not divert a joiner whose previous booking was cancelled', function () {
    $joiner = User::factory()->create(['role' => 'joiner']);
    Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id,
        'joiner_id' => $joiner->id,
        'status' => 'cancelled',
    ]);

    Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertNoRedirect();
});

/*
 * The oversell guard protects against a race, not against a careless number.
 *
 * The stepper now caps at seats remaining, so the only way to reach submit() asking for more than
 * exists is for someone else to take them while this form was being filled in — which is exactly
 * what happens here: the competing booking lands after the seat count is chosen.
 */
it('refuses to oversell and says how many seats are left', function () {
    $component = Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', 5);

    Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 9, 'status' => 'confirmed',
    ]);

    $component
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('createAccount', false);

    // Fully filled, so the booking is stopped by the seat guard and not merely by the traveller
    // rules — otherwise this would pass without the oversell check existing at all.
    foreach (range(1, 4) as $i) {
        $component->set("participants.{$i}.first_name", 'Guest')
            ->set("participants.{$i}.last_name", 'Number'.$i)
            ->set("participants.{$i}.email", "guest{$i}@example.com");
    }

    $component
        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->set('participants.1.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.1.emergency_contact_phone', '09171112222')
        ->set('participants.2.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.2.emergency_contact_phone', '09171112222')
        ->set('participants.3.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.3.emergency_contact_phone', '09171112222')
        ->set('participants.4.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.4.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('closed', false)
        ->assertNoRedirect();

    expect(Reservation::where('contact_email', 'ada@example.com')->count())->toBe(0);
});

it('pre-fills name parts from a legacy account that only has a display name', function () {
    // Every account predating structured name columns has only `name`. Without a fallback a
    // long-standing member would meet an emptier form than a first-time guest.
    $joiner = User::factory()->create([
        'role' => 'joiner', 'name' => 'Mika Ramos',
        'first_name' => null, 'last_name' => null,
    ]);

    Livewire::actingAs($joiner)
        ->test(Register::class, ['trip' => $this->trip])
        ->assertSet('contact.first_name', 'Mika')
        ->assertSet('contact.last_name', 'Ramos');
});

it('rejects a birthday with an implausible year', function () {
    // `before:today` alone accepts the year 0514, which a date input can produce from a typo.
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('participants.0.birthday', '0514-09-12')

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertHasErrors(['participants.0.birthday'])
        // The wording, not just the failure: these messages sat in a method Livewire never reads,
        // so a rename back to validationMessages() would make them silently inert again.
        ->assertSee('looks like a typo');
});

it('names fields in plain language when validation fails', function () {
    // Laravel would otherwise render "The contact.first name field is required."
    $component = Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->call('submit');

    $messages = collect($component->errors()->all())->implode(' ');

    expect($messages)->not->toContain('contact.')
        ->and($messages)->toContain('first name');
});

it('survives the seat field being cleared mid-edit', function () {
    // A `public int` cannot hold the "" Livewire sends when the box is emptied; PHP leaves the
    // typed property uninitialized and routes the next read to __get(), which threw a 500 for
    // the ordinary act of backspacing over a number.
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', '')
        ->assertOk()
        // The box stays empty rather than snapping to 1, which would rewrite it under the cursor.
        ->assertSet('partySize', '')
        // Totals still render, falling back to one seat rather than showing a price of zero.
        ->assertSee('3,500');
});

it('lets a two-digit seat count be typed a digit at a time', function () {
    // Snapping to a clamped value on every keystroke makes "12" impossible: clearing rewrites the
    // box to 1, so the next digit produces 11, not 12. Roomy departure so the cap is not what is
    // under test here.
    $this->departure->update(['capacity' => 30]);

    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', '')
        ->set('partySize', '1')
        ->set('partySize', '12')
        ->assertSet('partySize', 12)
        ->assertCount('participants', 12);
});

it('clamps a seat count to the seats actually left', function () {
    // capacity 10, four already taken
    Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 4, 'status' => 'confirmed',
    ]);

    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', '99')
        ->assertSet('partySize', 6)
        ->assertCount('participants', 6)
        ->set('partySize', '0')
        ->assertSet('partySize', 1);
});

it('never offers more than twenty seats however empty the departure', function () {
    $this->departure->update(['capacity' => 500]);

    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', '99')
        ->assertSet('partySize', 20);
});

it('steps the seat count up and down without letting it pass the seats left', function () {
    Reservation::factory()->create([
        'trip_departure_id' => $this->departure->id, 'party_size' => 8, 'status' => 'confirmed',
    ]);

    Livewire::test(Register::class, ['trip' => $this->trip])   // 2 left
        ->call('addSeat')
        ->assertSet('partySize', 2)
        ->call('addSeat')
        ->assertSet('partySize', 2)   // held at the ceiling, not walked into a full trip
        ->call('removeSeat')
        ->assertSet('partySize', 1)
        ->call('removeSeat')
        ->assertSet('partySize', 1);
});

it('refuses to submit with an empty seat field', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('partySize', '')
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')

        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->call('submit')
        ->assertHasErrors(['partySize'])
        ->assertNoRedirect();

    expect(Reservation::count())->toBe(0);
});

/*
 * ── Review before booking ────────────────────────────────────────────────────────────────
 */

it('refuses to open the review sheet over a form that would be rejected', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->call('review')
        ->assertHasErrors(['contact.first_name'])
        // Showing a tidy summary and only then reporting a missing field is worse than never
        // showing the summary at all.
        ->assertSet('showReview', false);

    expect(Reservation::count())->toBe(0);
});

it('opens the review sheet once everything checks out, without booking yet', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('contact.first_name', 'Ada')
        ->set('contact.last_name', 'Reyes')
        ->set('contact.email', 'ada@example.com')
        ->set('contact.phone', '09171234567')
        ->set('participants.0.emergency_contact_name', 'Rosa Reyes')
        ->set('participants.0.emergency_contact_phone', '09171112222')
        ->call('review')
        ->assertHasNoErrors()
        ->assertSet('showReview', true)
        ->assertSee('Check before booking')
        ->assertSeeHtml('dusk="confirm-booking"');

    // Reviewing is not booking.
    expect(Reservation::count())->toBe(0);
});

it('only hides the travellers section when the booker is the single traveller', function () {
    // escape: false is load-bearing. assertSee escapes its needle, so "Who's going?" was
    // hunting for "Who&#039;s going?" — a string the page never contains either way, which made
    // the assertDontSee half pass no matter what rendered.
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->assertDontSee("Who's going?", false)
        // one seat, booker travelling: their own fields live in Your details, no seat cards
        ->assertSeeHtml('dusk="seat-0-birthday"');

    // One seat booked for somebody else still needs a card to name them.
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->set('bookerIsTraveling', false)
        ->assertSee("Who's going?", false)
        ->assertSeeHtml('dusk="seat-0-first"');
});

/*
 * The toggle decides whether a travellers section is needed, so it must never live inside one.
 * It did, and hiding that section took the toggle with it — leaving a booker paying for someone
 * else no way to say they were not travelling.
 */
it('always offers the not-travelling toggle', function () {
    Livewire::test(Register::class, ['trip' => $this->trip])
        ->assertSeeHtml('dusk="booker-traveling"')
        ->set('partySize', 3)
        ->assertSeeHtml('dusk="booker-traveling"');
});
