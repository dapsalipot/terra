<?php

namespace App\Livewire\Reservations;

use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class Pay extends Component
{
    use WithFileUploads;

    public $proof = null;
    public Reservation $reservation;
    public string $method = '';
    public string $reference_no = '';

    public function mount(Reservation $reservation): void
    {
        abort_unless($reservation->joiner_id === Auth::id(), 403);
        $this->reservation = $reservation->load('departure.trip.host.hostProfile', 'latestPayment');
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
        return view('livewire.reservations.pay')->layout('components.layouts.site');
    }
}
