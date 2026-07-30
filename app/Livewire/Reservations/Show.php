<?php

namespace App\Livewire\Reservations;

use App\Models\Reservation;
use App\Services\ReservationService;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public Reservation $reservation;
    public $proof = null;
    public string $method = '';
    public string $reference_no = '';

    /**
     * Authorized by token possession, not by session.
     *
     * A guest booking has no joiner_id, so ownership alone cannot gate this page. The token is a
     * long random value scoped to exactly one booking — the standard guest order-status pattern.
     */
    public function mount(string $token): void
    {
        $this->reservation = Reservation::where('public_token', $token)
            ->with('departure.trip.host.hostProfile', 'latestPayment', 'participants')
            ->firstOrFail();
    }

    public function submit(ReservationService $service): void
    {
        $this->validate([
            'reference_no' => ['required', 'string', 'max:120'],
            'method' => ['nullable', 'string', 'max:60'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $service->submitPayment($this->reservation, [
            'method' => $this->method ?: null,
            'reference_no' => $this->reference_no,
            'proof_image' => $this->proof ? $this->proof->store('proofs', 'local') : null,
        ]);

        $this->reservation->refresh();
    }

    public function cancel(ReservationService $service)
    {
        $service->cancel($this->reservation, 'Cancelled by joiner');

        return $this->redirectRoute('home', navigate: true);
    }

    public function render()
    {
        return view('livewire.reservations.show')->layout('components.layouts.site');
    }
}
