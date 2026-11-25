<?php

namespace App\Livewire\Reservations;

use App\Models\Reservation;
use Livewire\Component;

/**
 * The joiner's check-in passes, one per traveller on the booking.
 *
 * Reached with the booking token like every other joiner-facing page, so a guest with no account
 * can still show a pass at the trailhead.
 */
class Pass extends Component
{
    public Reservation $reservation;

    public function mount(string $token): void
    {
        $this->reservation = Reservation::where('public_token', $token)
            ->with('departure.trip.host.hostProfile', 'participants')
            ->firstOrFail();

        // A pass only means something once the money is in and the booking is live.
        abort_if($this->reservation->status === 'cancelled', 404);
        abort_unless($this->reservation->participants->contains(fn ($p) => $p->pass_code), 404);
    }

    public function render()
    {
        return view('livewire.reservations.pass')
            ->layout('components.layouts.site', ['title' => 'Terra · Check-in pass', 'tabbar' => false]);
    }
}
