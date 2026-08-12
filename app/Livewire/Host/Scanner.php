<?php

namespace App\Livewire\Host;

use App\Models\ReservationParticipant;
use App\Models\TripDeparture;
use App\Services\CheckInService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Check-in at the trailhead.
 *
 * The camera lives entirely in the browser; this component only ever sees a code string. That
 * keeps the server side identical whether the code arrived from a scan or was typed in by hand,
 * which matters because a dead phone or a cracked lens is a normal Tuesday on a mountain.
 */
class Scanner extends Component
{
    public TripDeparture $departure;

    public string $code = '';

    /** Last result, kept as a pair so the view can style success and failure differently. */
    public ?string $lastMessage = null;

    public bool $lastOk = false;

    public function mount(TripDeparture $departure): void
    {
        abort_unless($departure->trip->host_id === Auth::id(), 403);

        $this->departure = $departure;
    }

    public function scan(CheckInService $service): void
    {
        $code = trim($this->code);
        $this->code = '';

        if ($code === '') {
            return;
        }

        try {
            $participant = $service->checkIn($code, Auth::user(), $this->departure->id);

            $this->lastOk = true;
            $this->lastMessage = $participant->fullName().' is in.';
        } catch (\RuntimeException $e) {
            $this->lastOk = false;
            $this->lastMessage = $e->getMessage();
        }
    }

    public function undo(int $participantId, CheckInService $service): void
    {
        $participant = ReservationParticipant::findOrFail($participantId);

        $service->undoCheckIn($participant, Auth::user());

        $this->lastOk = false;
        $this->lastMessage = $participant->fullName().' is no longer checked in.';
    }

    public function render()
    {
        $participants = $this->departure->reservations()
            ->whereNot('status', 'cancelled')
            ->with('participants')
            ->get()
            ->flatMap->participants
            ->sortBy(fn ($p) => $p->fullName())
            ->values();

        return view('livewire.host.scanner', [
            'participants' => $participants,
            'inCount' => $participants->whereNotNull('checked_in_at')->count(),
        ])->layout('components.layouts.host', ['title' => 'Terra · Check-in']);
    }
}
