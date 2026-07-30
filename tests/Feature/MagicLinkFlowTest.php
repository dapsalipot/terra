<?php

use App\Mail\MagicLinkMail;
use App\Models\Reservation;
use App\Models\User;
use App\Services\MagicLinkService;
use Illuminate\Support\Facades\Mail;

it('signs a user in through a login link', function () {
    $user = User::factory()->create(['role' => 'joiner', 'password' => null]);
    ['url' => $url] = app(MagicLinkService::class)->issue('login', ['user_id' => $user->id]);

    $this->get($url)->assertRedirect();

    expect(auth()->id())->toBe($user->id);
});

it('refuses a consumed login link the second time', function () {
    $user = User::factory()->create(['role' => 'joiner']);
    ['url' => $url] = app(MagicLinkService::class)->issue('login', ['user_id' => $user->id]);

    $this->get($url);
    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();
});

it('refuses an expired login link', function () {
    $user = User::factory()->create(['role' => 'joiner']);
    ['url' => $url] = app(MagicLinkService::class)->issue('login', ['user_id' => $user->id]);

    $this->travel(31)->minutes();

    $this->get($url)->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();
});

it('attaches a booking to its owner through an attach link', function () {
    $user = User::factory()->create(['role' => 'joiner']);
    $reservation = Reservation::factory()->create(['joiner_id' => null]);

    ['url' => $url] = app(MagicLinkService::class)->issue('attach', [
        'user_id' => $user->id,
        'reservation_id' => $reservation->id,
    ]);

    $this->get($url)->assertRedirect(route('reservations.show', ['token' => $reservation->public_token]));

    expect($reservation->fresh()->joiner_id)->toBe($user->id)
        ->and(auth()->id())->toBe($user->id);
});

it('will not hand a booking to a link with no user attached', function () {
    // An invite-style link (Spec 2) carries no user_id and must never authenticate or attach.
    $reservation = Reservation::factory()->create(['joiner_id' => null]);

    ['url' => $url] = app(MagicLinkService::class)->issue('attach', [
        'email' => 'stranger@example.com',
        'reservation_id' => $reservation->id,
    ]);

    $this->get($url);

    expect(auth()->check())->toBeFalse()
        ->and($reservation->fresh()->joiner_id)->toBeNull();
});

it('emails a sign-in link on request', function () {
    Mail::fake();
    $user = User::factory()->create(['role' => 'joiner']);

    $this->post(route('magic.send'), ['email' => $user->email])->assertRedirect();

    Mail::assertSent(MagicLinkMail::class);
});

it('does not reveal whether an address is registered', function () {
    Mail::fake();

    $this->post(route('magic.send'), ['email' => 'nobody@example.com'])
        ->assertRedirect()
        ->assertSessionHas('status');

    Mail::assertNothingSent();
});

it('still issues sign-in links even though the login page no longer offers a form', function () {
    // The login-page form was removed in favour of social sign-in, but the mechanism is still
    // load-bearing: it backs the post-registration email and booking attachment.
    Mail::fake();
    $user = User::factory()->create(['role' => 'joiner']);

    $this->post(route('magic.send'), ['email' => $user->email])->assertRedirect();

    Mail::assertSent(MagicLinkMail::class);
    expect(App\Models\MagicLink::where('purpose', 'login')->count())->toBe(1);
});

it('does not lose a booking when the mail transport fails', function () {
    // The send happens inside register()'s transaction, so an uncaught transport error would
    // roll back the reservation and its traveller rows. The booking must survive.
    Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp is down'));

    $departure = App\Models\TripDeparture::factory()->create(['capacity' => 5]);

    $result = app(App\Services\RegistrationService::class)->register($departure, [
        'party_size' => 1,
        'contact' => [
            'first_name' => 'Ada', 'middle_name' => null, 'last_name' => 'Reyes',
            'email' => 'ada@example.com', 'phone' => '09171234567',
        ],
        'booker_is_traveling' => true,
        'participants' => [['first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02']],
        'create_account' => true,
        'actor' => null,
    ]);

    expect($result->reservation->exists)->toBeTrue()
        ->and($result->outcome)->toBe(App\Support\AccountOutcome::AccountCreated)
        ->and(User::where('email', 'ada@example.com')->exists())->toBeTrue()
        ->and(Reservation::count())->toBe(1);
});
