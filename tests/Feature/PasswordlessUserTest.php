<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

it('stores profile fields used to pre-fill a later booking', function () {
    $user = User::factory()->create([
        'first_name' => 'Ada', 'middle_name' => 'Luz', 'last_name' => 'Reyes',
        'birthday' => '1998-04-02', 'gender' => 'female',
    ]);

    expect($user->fresh()->full_name)->toBe('Ada Luz Reyes')
        ->and($user->fresh()->birthday->format('Y-m-d'))->toBe('1998-04-02');
});

it('creates an account with no password at all', function () {
    $user = User::factory()->create(['password' => null]);

    expect($user->fresh()->password)->toBeNull();
});

it('refuses to authenticate a passwordless account with any password', function () {
    $user = User::factory()->create(['password' => null]);

    // Hash::check returns false for a null hash, so no submitted value can ever match.
    expect(Hash::check('password', $user->password))->toBeFalse()
        ->and(Hash::check('', $user->password))->toBeFalse();

    // This app authenticates through the Volt login component (routes/auth.php only
    // registers a GET route for "login"), so we exercise it the way the app really
    // does, not via a POST to a route that doesn't exist.
    $component = Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasErrors()
        ->assertNoRedirect();

    $this->assertGuest();
});
