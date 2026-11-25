<?php

use Illuminate\Support\Facades\Route;

// Public marketplace (Tarana 01)
Route::get('/', \App\Livewire\Home::class)->name('home');
Route::get('/trips', \App\Livewire\Trips\Index::class)->name('trips.index');
Route::get('/t/{trip:slug}', \App\Livewire\Trips\Show::class)->name('trips.show');
// Throttled: reserve() holds a seat for hours, so an unthrottled endpoint lets one person
// empty a departure with junk holds and leave it reading sold out until they expire.
Route::get('/t/{trip:slug}/join', \App\Livewire\Reservations\Register::class)
    ->middleware('throttle:30,1')->name('trips.join');
Route::get('/hosts', \App\Livewire\Hosts\Index::class)->name('hosts.index');
Route::get('/@{user:handle}', \App\Livewire\Hosts\Show::class)->name('hosts.show');

// After login/registration, route by role instead of the stock Breeze dashboard. Hosts get the
// console; joiners get their own trips, which until now had no home at all — their bookings were
// reachable only through the /r/{token} link they were told to save.
Route::get('dashboard', function () {
    return redirect()->route(auth()->user()?->isHost() ? 'host.dashboard' : 'my.trips');
})->middleware(['auth'])->name('dashboard');

// A joiner's own trips. Their own URL rather than a bare /dashboard, so it can be linked and
// bookmarked like anything else on the site.
Route::get('/my-trips', \App\Livewire\Joiner\Dashboard::class)
    ->middleware('auth')->name('my.trips');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

/*
 * Social sign-in. Constrained to known providers so an unknown one 404s rather than reaching
 * Socialite, and available while signed in so a user can attach a second provider.
 */
Route::get('/auth/{provider}/redirect', [\App\Http\Controllers\SocialAuthController::class, 'redirect'])
    ->where('provider', \App\Support\SocialProviders::PATTERN)
    ->name('social.redirect');
Route::get('/auth/{provider}/callback', [\App\Http\Controllers\SocialAuthController::class, 'callback'])
    ->where('provider', \App\Support\SocialProviders::PATTERN)
    ->name('social.callback');

/*
 * Magic links: passwordless sign-in, and attaching a guest booking to its rightful owner.
 * Issuance is throttled so the endpoint cannot be used to mail-bomb an address.
 */
Route::get('/magic/{token}', \App\Http\Controllers\MagicLinkController::class)->name('magic.consume');
Route::post('/magic', \App\Http\Controllers\SendMagicLinkController::class)
    ->middleware('throttle:5,1')->name('magic.send');


/*
 * Booking status and payment.
 *
 * Deliberately outside `auth`: a guest booking has no user to authenticate, so possession of
 * the token is the credential. The page exposes exactly one booking and no account data.
 */
Route::get('/r/{token}', \App\Livewire\Reservations\Show::class)->name('reservations.show');
// The receipt the joiner uploaded, served on the strength of the booking token they already hold.
Route::get('/r/{token}/proof', \App\Http\Controllers\BookingProofController::class)->name('reservations.proof');
// A traveller ID photo. Guarded in the controller, not by a route group: the host reaches it
// while signed in, the joiner reaches it with the booking token, and nobody else at all.
Route::get('/ids/{participant}', \App\Http\Controllers\ParticipantIdController::class)->name('participants.id');
// The QR square itself, and the pass it sits on. Both guarded the same way as the ID above.
Route::get('/passes/{participant}/qr', \App\Http\Controllers\PassQrController::class)->name('passes.qr');
Route::get('/r/{token}/pass', \App\Livewire\Reservations\Pass::class)->name('reservations.pass');

// Calendar. Subscribed by a calendar server with no session, so the token in the URL is the
// whole credential — rotating it revokes every subscription.
Route::get('/calendar/{token}.ics', [\App\Http\Controllers\CalendarFeedController::class, 'feed'])
    ->name('calendar.feed');
Route::get('/r/{token}/trip.ics', [\App\Http\Controllers\CalendarFeedController::class, 'booking'])
    ->name('calendar.booking');

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
    Route::get('/departures/{departure}/check-in', \App\Livewire\Host\Scanner::class)->name('host.checkin');
    Route::get('/trips/{trip:slug}/qr', \App\Http\Controllers\TripQrController::class)->name('host.trips.qr');
    Route::get('/proof/{payment}', \App\Http\Controllers\ProofController::class)->name('host.proof');
});

/*
 * The admin area.
 *
 * Not nested under /host: a moderator is not a seller, and putting the two behind the same
 * prefix invites the mistake of guarding one with the other s middleware.
 */
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('admin.dashboard');
});

require __DIR__.'/auth.php';

/*
 * Root-level host pages: /trailco
 *
 * Registered last so every named route above wins, and constrained to a handle shape so
 * it cannot swallow an unmatched URL and turn a 404 into a "host not found".
 * `/@handle` stays as an alias — links already exist in the wild.
 */
Route::get('/{user:handle}', \App\Livewire\Hosts\Show::class)
    ->where('user', \App\Support\Handles::PATTERN)
    ->name('hosts.handle');
