<?php

use Illuminate\Support\Facades\Route;

// Public marketplace (Peeps 01)
Route::get('/', \App\Livewire\Home::class)->name('home');
Route::get('/trips', \App\Livewire\Trips\Index::class)->name('trips.index');
Route::get('/t/{trip:slug}', \App\Livewire\Trips\Show::class)->name('trips.show');
Route::get('/hosts', \App\Livewire\Hosts\Index::class)->name('hosts.index');
Route::get('/@{user:handle}', \App\Livewire\Hosts\Show::class)->name('hosts.show');

// After login/registration, route by role instead of the stock Breeze dashboard.
Route::get('dashboard', function () {
    return auth()->user()?->isHost()
        ? redirect()->route('host.dashboard')
        : redirect()->route('home');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Magic links. The controller arrives in Task 7; this reserves the route name so
// MagicLinkService::issue() can build URLs.
Route::get('/magic/{token}', fn (string $token) => abort(404))->name('magic.consume');


/*
 * Booking status and payment.
 *
 * Deliberately outside `auth`: a guest booking has no user to authenticate, so possession of
 * the token is the credential. The page exposes exactly one booking and no account data.
 */
Route::get('/r/{token}', \App\Livewire\Reservations\Show::class)->name('reservations.show');

/*
 * Legacy URL kept alive so links already sent out keep working.
 *
 * Ownership-checked, unlike /r/{token} itself. This route takes a guessable integer id, so
 * without the check it would hand the unguessable public_token to anyone who counted upwards —
 * defeating the point of token authorization.
 */
Route::get('/reservations/{reservation}/pay', function (\App\Models\Reservation $reservation) {
    // The null check is load-bearing, not defensive noise: a guest booking has joiner_id null
    // and an unauthenticated auth()->id() is also null, so a bare === would compare null to
    // null and pass. The auth middleware happens to stop that today; this does not depend on it.
    abort_unless($reservation->joiner_id !== null && $reservation->joiner_id === auth()->id(), 403);

    return redirect()->route('reservations.show', ['token' => $reservation->public_token]);
})->middleware('auth')->name('reservations.pay');

// Reservation flow + host roster (Plan 02)
Route::middleware('auth')->group(function () {
    Route::get('/chat/{departure}', \App\Livewire\Chat\Room::class)->name('chat.show');

    Route::post('/logout', function () {
        auth()->guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');
});


// Host management (Plan 03)
Route::middleware(['auth', 'host'])->prefix('host')->group(function () {
    Route::get('/', \App\Livewire\Host\Dashboard::class)->name('host.dashboard');
    Route::get('/profile', \App\Livewire\Host\Profile::class)->name('host.profile');
    Route::get('/meetup-points', \App\Livewire\Host\MeetupPresets::class)->name('host.meetup-presets');
    Route::get('/trips', \App\Livewire\Host\Trips\Index::class)->name('host.trips.index');
    Route::get('/trips/create', \App\Livewire\Host\Trips\Create::class)->name('host.trips.create');
    // same wizard, editing one existing schedule in place
    Route::get('/trips/{trip:slug}/edit', \App\Livewire\Host\Trips\Create::class)->name('host.trips.edit');
    // same wizard, pre-loaded from a trip the host has already run
    Route::get('/trips/create/from/{from:slug}', \App\Livewire\Host\Trips\Create::class)->name('host.trips.duplicate');
    Route::get('/departures/{departure}/roster', \App\Livewire\Host\Roster::class)->name('host.roster');
    Route::get('/trips/{trip:slug}/qr', \App\Http\Controllers\TripQrController::class)->name('host.trips.qr');
    Route::get('/proof/{payment}', \App\Http\Controllers\ProofController::class)->name('host.proof');
});

require __DIR__.'/auth.php';

/*
 * Root-level host pages: peeps.test/trailco
 *
 * Registered last so every named route above wins, and constrained to a handle shape so
 * it cannot swallow an unmatched URL and turn a 404 into a "host not found".
 * `/@handle` stays as an alias — links already exist in the wild.
 */
Route::get('/{user:handle}', \App\Livewire\Hosts\Show::class)
    ->where('user', \App\Support\Handles::PATTERN)
    ->name('hosts.handle');
