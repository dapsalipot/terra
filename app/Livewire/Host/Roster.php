<?php

namespace App\Livewire\Host;

use App\Models\Reservation;
use App\Models\TripDeparture;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Roster extends Component
{
    public TripDeparture $departure;
    public array $rejectReason = [];

    // Add-offline-participant form
    public bool $showAdd = false;
    public string $guest_name = '';
    public int $guest_party = 1;

    public function mount(TripDeparture $departure): void
    {
        $departure->load('trip.place', 'trip.priceTiers');
        abort_unless($departure->trip->host_id === Auth::id(), 403);
        $this->departure = $departure;
    }

    public function confirm(int $reservationId, ReservationService $service): void
    {
        $service->confirm($this->guardedReservation($reservationId), Auth::user());
    }

    public function reject(int $reservationId, ReservationService $service): void
    {
        $reason = trim($this->rejectReason[$reservationId] ?? '') ?: 'Payment could not be verified';
        $service->reject($this->guardedReservation($reservationId), Auth::user(), $reason);
        unset($this->rejectReason[$reservationId]);
    }

    public function addOffline(ReservationService $service): void
    {
        $this->validate([
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_party' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $service->addManualParticipant($this->departure->fresh(), [
            'guest_name' => $this->guest_name,
            'party_size' => $this->guest_party,
        ]);

        $this->reset('guest_name', 'guest_party', 'showAdd');
        $this->guest_party = 1;
    }

    private function guardedReservation(int $id): Reservation
    {
        return Reservation::where('trip_departure_id', $this->departure->id)->findOrFail($id);
    }

    public function render()
    {
        $priority = ['payment_submitted' => 0, 'awaiting_payment' => 1, 'pending' => 2, 'confirmed' => 3, 'cancelled' => 4];

        $reservations = $this->departure->reservations()
            ->with('joiner', 'latestPayment', 'participants')
            ->get()
            ->sortBy(fn ($r) => $priority[$r->status] ?? 9)
            ->values();

        // Money the host is owed, derived per reservation. Cancelled bookings are worth
        // nothing and must not inflate the expected total.
        $live = $reservations->reject(fn ($r) => $r->status === 'cancelled');

        $money = [
            'expected' => $live->sum(fn ($r) => $r->money()['total']),
            'collected' => $live->sum(fn ($r) => $r->money()['paid']),
        ];
        $money['outstanding'] = $money['expected'] - $money['collected'];

        return view('livewire.host.roster', [
            'reservations' => $reservations,
            'money' => $money,
            'currency' => Auth::user()->hostProfile?->currency ?? 'PHP',
            'trip' => $this->departure->trip,
        ])->layout('components.layouts.host', ['title' => 'Peeps · '.$this->departure->trip->title]);
    }
}
