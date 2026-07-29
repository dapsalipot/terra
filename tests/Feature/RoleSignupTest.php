<?php

use App\Models\User;
use Livewire\Volt\Volt;

it('registers a host with a handle and a host profile', function () {
    Volt::test('pages.auth.register')
        ->set('name', 'Ridgeline')
        ->set('email', 'ridge@example.com')
        ->set('role', 'host')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register');

    $host = User::where('email', 'ridge@example.com')->first();
    expect($host->role)->toBe('host')
        ->and($host->handle)->toBe('ridgeline')
        ->and($host->isHost())->toBeTrue()
        ->and($host->hostProfile)->not->toBeNull()
        ->and($host->hostProfile->currency)->toBe('PHP');
});

it('registers a joiner with no handle or host profile', function () {
    Volt::test('pages.auth.register')
        ->set('name', 'Mika Ramos')
        ->set('email', 'mika@example.com')
        ->set('role', 'joiner')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register');

    $joiner = User::where('email', 'mika@example.com')->first();
    expect($joiner->role)->toBe('joiner')
        ->and($joiner->handle)->toBeNull()
        ->and($joiner->hostProfile)->toBeNull();
});

it('rejects an invalid role', function () {
    Volt::test('pages.auth.register')
        ->set('name', 'X')
        ->set('email', 'x@example.com')
        ->set('role', 'driver')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register')
        ->assertHasErrors('role');
});
