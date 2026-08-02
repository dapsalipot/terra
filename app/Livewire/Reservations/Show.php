<?php

namespace App\Livewire\Reservations;

use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Services\ReservationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use AuthorizesRequests, WithFileUploads;

    public Reservation $reservation;
    public $proof = null;
    public string $method = '';
    public string $reference_no = '';

    public $editingId = null;
    public bool $showEditModal = false;
    public $edit_first_name = '';
    public $edit_middle_name = '';
    public $edit_last_name = '';
    public $edit_birthday = '';
    public $edit_gender = '';
    public $edit_email = '';
    public $edit_phone = '';

    public $removingId = null;
    public $removingName = '';
    public bool $removingLast = false;

    public function mount(string $token): void
    {
        $this->reservation = Reservation::where('public_token', $token)
            ->with('departure.trip.host.hostProfile', 'latestPayment', 'participants')
            ->firstOrFail();
    }

    public function canEdit(): bool
    {
        return auth()->check() && $this->reservation->joiner_id === auth()->id()
            || ! $this->reservation->joiner_id;
    }

    public function editParticipant(int $id): void
    {
        $this->authorizeEdit();

        $participant = $this->reservation->participants->firstWhere('id', $id);

        if (! $participant) {
            return;
        }

        $this->editingId = $id;
        $this->edit_first_name = $participant->first_name ?? '';
        $this->edit_middle_name = $participant->middle_name ?? '';
        $this->edit_last_name = $participant->last_name ?? '';
        $this->edit_birthday = $participant->birthday?->format('Y-m-d') ?? '';
        $this->edit_gender = $participant->gender ?? '';
        $this->edit_email = $participant->email ?? '';
        $this->edit_phone = $participant->phone ?? '';
        $this->showEditModal = true;
    }

    public function updateParticipant(): void
    {
        $this->authorizeEdit();

        $participant = $this->reservation->participants->firstWhere('id', $this->editingId);

        if (! $participant) {
            $this->closeModal();

            return;
        }

        $this->validate([
            'edit_first_name' => ['required', 'string', 'max:120'],
            'edit_middle_name' => ['nullable', 'string', 'max:120'],
            'edit_last_name' => ['required', 'string', 'max:120'],
            'edit_birthday' => ['required', 'date'],
            'edit_gender' => ['nullable', 'in:male,female,other,undisclosed'],
            'edit_email' => ['nullable', 'email', 'max:120'],
            'edit_phone' => ['nullable', 'string', 'max:60'],
        ]);

        $participant->update([
            'first_name' => $this->edit_first_name,
            'middle_name' => $this->edit_middle_name ?: null,
            'last_name' => $this->edit_last_name,
            'birthday' => $this->edit_birthday,
            'gender' => $this->edit_gender ?: null,
            'email' => $this->edit_email ?: null,
            'phone' => $this->edit_phone ?: null,
        ]);

        $this->reservation->refresh();
        $this->closeModal();
    }

    public function cancelEdit(): void
    {
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showEditModal = false;
        $this->editingId = null;
    }

    public function confirmRemove(int $id): void
    {
        $this->authorizeEdit();

        $participant = $this->reservation->participants->firstWhere('id', $id);

        if (! $participant) {
            return;
        }

        $this->removingId = $id;
        $this->removingName = $participant->fullName() ?: 'this traveller';
        $this->removingLast = $this->reservation->participants->count() <= 1;
    }

    public function cancelRemove(): void
    {
        $this->removingId = null;
        $this->removingName = '';
    }

    public function removeParticipant(int $id, ReservationService $service)
    {
        $this->authorizeEdit();

        $participant = $this->reservation->participants->firstWhere('id', $id);

        if (! $participant) {
            $this->removingId = null;
            $this->removingName = '';

            return;
        }

        if ($this->reservation->participants->count() <= 1) {
            $participant->delete();
            $service->cancel($this->reservation, 'All travellers removed');

            return $this->redirectRoute('home', navigate: true);
        }

        $participant->delete();

        $this->reservation->participants()
            ->where('position', '>', $participant->position)
            ->update(['position' => DB::raw('position - 1')]);

        $this->reservation->update([
            'party_size' => $this->reservation->participants->count() - 1,
        ]);

        $this->reservation->refresh();
        $this->removingId = null;
        $this->removingName = '';
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

    private function authorizeEdit(): void
    {
        $isJoiner = auth()->check() && $this->reservation->joiner_id === auth()->id();
        $isGuest = ! $this->reservation->joiner_id;

        abort_if(! $isJoiner && ! $isGuest, 403);
    }

    public function render()
    {
        return view('livewire.reservations.show')->layout('components.layouts.site');
    }
}
