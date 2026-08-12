<?php

namespace App\Http\Controllers;

use App\Models\MagicLink;
use App\Models\Reservation;
use App\Services\MagicLinkService;
use Illuminate\Support\Facades\Auth;

class MagicLinkController extends Controller
{
    public function __invoke(string $token, MagicLinkService $links)
    {
        $link = $links->consume($token);

        if (! $link) {
            return redirect()->route('login')
                ->with('status', 'That link has expired or was already used. Ask for a new one below.');
        }

        /*
         * Only a link we emailed to an address may create a session, which is why this keys off
         * the link's own user_id rather than anything in the request. Spec 2's host-shared invite
         * links carry no user_id — a link pasted into a group chat must never log anyone in, and
         * must never attach a stranger's booking either.
         */
        if ($link->user) {
            Auth::login($link->user, remember: true);

            if ($link->purpose === 'attach') {
                $this->attachBookings($link);
            }
        }

        if ($link->reservation) {
            return redirect()->route('reservations.show', ['token' => $link->reservation->public_token]);
        }

        return redirect()->route('home');
    }

    /**
     * Attach the named booking, and any other guest booking made with the same address.
     *
     * Clicking a link we emailed proves control of that mailbox, which is exactly the claim every
     * one of those bookings needs. One link per booking would be noise, and leaving the rest
     * behind would send someone hunting for /r/ links they may never have saved.
     */
    private function attachBookings(MagicLink $link): void
    {
        $link->reservation?->update(['joiner_id' => $link->user->id]);

        if (blank($link->email)) {
            return;
        }

        Reservation::whereNull('joiner_id')
            ->where('contact_email', $link->email)
            ->update(['joiner_id' => $link->user->id]);
    }
}
