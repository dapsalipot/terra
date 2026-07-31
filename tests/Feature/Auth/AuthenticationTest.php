<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSeeVolt('pages.auth.login');
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'wrong-password');

    $component->call('login');

    $component
        ->assertHasErrors()
        ->assertNoRedirect();

    $this->assertGuest();
});

test('dashboard routes joiners to their trips and hosts to their console', function () {
    // Joiners used to be bounced to the marketplace, which left their bookings reachable only
    // through the /r/{token} link they were told to save.
    $joiner = User::factory()->create(['role' => 'joiner']);
    $this->actingAs($joiner)->get('/dashboard')->assertRedirect(route('my.trips'));

    $host = User::factory()->create(['role' => 'host']);
    $this->actingAs($host)->get('/dashboard')->assertRedirect(route('host.dashboard'));
});

test('an unverified account can still reach its own trips', function () {
    // Registration and social sign-up both create accounts without sending a verification email,
    // so a `verified` guard here would strand people on a page they can never clear.
    $joiner = User::factory()->create(['role' => 'joiner', 'email_verified_at' => null]);

    $this->actingAs($joiner)->get(route('my.trips'))->assertOk();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Volt::test('layout.navigation');

    $component->call('logout');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
});
