<?php

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialAuthService;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    $this->svc = app(SocialAuthService::class);

    // Providers are only offered when configured, so the routes need credentials present.
    config([
        'services.google.client_id' => 'test-google-id',
        'services.google.client_secret' => 'test-google-secret',
        'services.facebook.client_id' => 'test-fb-id',
        'services.facebook.client_secret' => 'test-fb-secret',
    ]);
});

/** A Socialite user as a provider would hand it back. */
function socialUser(array $attrs = []): SocialiteUser
{
    $user = new SocialiteUser;
    $user->id = $attrs['id'] ?? 'provider-sub-123';
    $user->name = $attrs['name'] ?? 'Ada Reyes';
    // array_key_exists, not ??: passing an explicit null must mean "provider sent no email",
    // which is the whole point of the no-email test.
    $user->email = array_key_exists('email', $attrs) ? $attrs['email'] : 'ada@example.com';
    $user->avatar = $attrs['avatar'] ?? 'https://example.test/a.jpg';
    $user->user = $attrs['raw'] ?? ['email_verified' => true, 'given_name' => 'Ada', 'family_name' => 'Reyes'];

    return $user;
}

// ───────────────────────────────────────────────────────────── new accounts

it('creates a passwordless joiner on first sign-in', function () {
    $user = $this->svc->resolve('google', socialUser());

    expect($user->email)->toBe('ada@example.com')
        ->and($user->password)->toBeNull()
        ->and($user->role)->toBe('joiner')
        ->and($user->first_name)->toBe('Ada')
        ->and($user->last_name)->toBe('Reyes')
        ->and($user->email_verified_at)->not->toBeNull()   // Google asserted email_verified
        ->and(SocialAccount::where('user_id', $user->id)->where('provider', 'google')->exists())->toBeTrue();
});

it('never mints a host account from a social callback', function () {
    // Role escalation via a provider callback would be the worst possible outcome here.
    expect($this->svc->resolve('google', socialUser())->role)->toBe('joiner');
});

it('does not mark the email verified when the provider makes no such claim', function () {
    // Facebook exposes no verification flag; inventing one would launder an unverified address.
    $user = $this->svc->resolve('facebook', socialUser(['raw' => ['name' => 'Ada Reyes']]));

    expect($user->email_verified_at)->toBeNull();
});

// ───────────────────────────────────────────────────── returning accounts

it('recognises a returning user by provider id, not by email', function () {
    $first = $this->svc->resolve('google', socialUser(['email' => 'old@example.com']));

    // Same provider subject, different email — the person changed their Google address.
    $second = $this->svc->resolve('google', socialUser(['email' => 'new@example.com']));

    expect($second->id)->toBe($first->id)
        ->and(User::count())->toBe(1)
        ->and(SocialAccount::count())->toBe(1);
});

it('links to an existing account when the email matches', function () {
    $existing = User::factory()->create([
        'email' => 'ada@example.com', 'role' => 'joiner', 'email_verified_at' => null,
    ]);

    $user = $this->svc->resolve('google', socialUser());

    expect($user->id)->toBe($existing->id)
        ->and(User::count())->toBe(1)
        ->and(SocialAccount::where('user_id', $existing->id)->count())->toBe(1);
});

it('lets a passwordless registration account be claimed through its provider', function () {
    // This is the path that replaces the removed magic-link form on the login page.
    $passwordless = User::factory()->create([
        'email' => 'ada@example.com', 'password' => null, 'role' => 'joiner',
        // The factory verifies by default; a registration-created account has not been verified,
        // so leaving the default here would make the assertion below pass for the wrong reason.
        'email_verified_at' => null,
    ]);

    $user = $this->svc->resolve('google', socialUser());

    expect($user->id)->toBe($passwordless->id)
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});

// ─────────────────────────────────────────────────────────── failure paths

it('refuses when the provider shares no email', function () {
    // Facebook returns none for accounts registered by phone. There is nothing safe to do:
    // email is the booking identity everywhere else in the app.
    expect(fn () => $this->svc->resolve('facebook', socialUser(['email' => null])))
        ->toThrow(RuntimeException::class, 'did not share an email');

    expect(User::count())->toBe(0);
});

it('refuses when the provider gives no id', function () {
    expect(fn () => $this->svc->resolve('google', socialUser(['id' => ''])))
        ->toThrow(RuntimeException::class);
});

it('refuses an unsupported provider', function () {
    expect(fn () => $this->svc->resolve('myspace', socialUser()))
        ->toThrow(RuntimeException::class, 'Unsupported');
});

// ────────────────────────────────────────────────────── linking a second one

it('attaches a second provider to the signed-in account', function () {
    $actor = User::factory()->create(['email' => 'ada@example.com', 'role' => 'joiner']);

    $this->svc->resolve('google', socialUser(), $actor);
    $this->svc->resolve('facebook', socialUser(['id' => 'fb-999']), $actor);

    expect(SocialAccount::where('user_id', $actor->id)->count())->toBe(2)
        ->and(User::count())->toBe(1);
});

it('will not steal a provider account already linked elsewhere', function () {
    $owner = $this->svc->resolve('google', socialUser());
    $other = User::factory()->create(['email' => 'someone@example.com', 'role' => 'joiner']);

    expect(fn () => $this->svc->resolve('google', socialUser(), $other))
        ->toThrow(RuntimeException::class, 'already linked');

    expect(SocialAccount::where('provider_id', 'provider-sub-123')->first()->user_id)->toBe($owner->id);
});

it('keeps one row per user per provider on repeat sign-ins', function () {
    $this->svc->resolve('google', socialUser());
    $this->svc->resolve('google', socialUser());
    $this->svc->resolve('google', socialUser());

    expect(SocialAccount::count())->toBe(1);
});

// ─────────────────────────────────────────────────────────────────── routes

it('offers the configured providers on the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Continue with Google')
        ->assertSee('Continue with Facebook');
});

it('no longer offers the emailed sign-in link on the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Email me a sign-in link');
});

it('still offers a recovery route for an account with no password', function () {
    // With the magic-link form gone, password reset is the fallback for anyone whose email is
    // not a social account. Without this the passwordless accounts would be unreachable.
    $this->get(route('login'))->assertSee('Forgot your password?');
});

it('404s an unknown provider rather than reaching socialite', function () {
    $this->get('/auth/myspace/redirect')->assertNotFound();
});

it('hides social buttons entirely when nothing is configured', function () {
    config(['services.google.client_id' => null, 'services.facebook.client_id' => null]);

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('Continue with Google')
        ->assertDontSee('Continue with Facebook');
});

it('redirects to the provider', function () {
    $response = $this->get(route('social.redirect', 'google'));

    $response->assertRedirectContains('accounts.google.com');
});
