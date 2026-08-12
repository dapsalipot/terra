<?php

namespace App\Livewire\Reservations;

use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use AuthorizesRequests, WithFileUploads;

    public Reservation $reservation;

    public $proof = null;

    public string $method = '';

    public string $reference_no = '';

    /**
     * Whether the mobile payment sheet is open.
     *
     * Server-side rather than Alpine-only: uploading a receipt is a Livewire round trip, and a
     * plain `x-data="{ pay: false }"` was re-initialised when the component re-rendered — so the
     * sheet slammed shut the moment someone attached their screenshot. Entangled, the state
     * survives every render the upload causes.
     */
    public bool $payOpen = false;

    /** Whether the submitted-receipt sheet is open. Entangled for the same reason as $payOpen. */
    public bool $proofOpen = false;

    public $editingId = null;

    public bool $showEditModal = false;

    public $edit_first_name = '';

    public $edit_middle_name = '';

    public $edit_last_name = '';

    public $edit_birthday = '';

    public $edit_gender = '';

    public $edit_email = '';

    public $edit_phone = '';

    /** A staged ID photo for the participant open in the edit dialog. */
    public $edit_id = null;

    public $removingId = null;

    public $removingName = '';

    public bool $removingLast = false;

    /** Labels the host actually offers — the only values the method field may take. */
    public function getPayMethodsProperty(): array
    {
        return $this->reservation->departure->trip->host->hostProfile?->payment_methods ?? [];
    }

    public function mount(string $token): void
    {
        $this->reservation = Reservation::where('public_token', $token)
            ->with('departure.trip.host.hostProfile', 'departure.trip.photos', 'departure.trip.place', 'latestPayment', 'participants')
            ->firstOrFail();

        /*
         * Preselect the first method the host offers.
         *
         * The form carried a $method property that nothing ever wrote to, so every submission
         * recorded a null method and the host's roster could never say how the money arrived.
         * A checkout asks which one you used; this makes the answer the default rather than a
         * field someone has to remember to fill.
         */
        $this->method = $this->payMethods[0]['label'] ?? '';
    }

    /**
     * Same cover precedence as the join page: the chosen cover first, then the rest of the
     * album, and the stock fallback only when the trip has no photography of its own.
     */
    public function getGalleryProperty(): array
    {
        $trip = $this->reservation->departure->trip;
        $shot = fn (?string $url) => $url ? ['url' => $url, 'alt' => $trip->title] : null;

        $gallery = collect([$trip->cover_photo ? $shot($trip->cover_url) : null])
            ->concat($trip->photos->map(fn ($p) => $shot($p->url)))
            ->filter()
            ->values();

        return $gallery->isNotEmpty()
            ? $gallery->all()
            : array_filter([$shot($trip->cover_url)]);
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
        $this->edit_id = null;
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
            // Only an image, and small enough that a phone photo goes through but a scan of a
            // whole passport book does not sit in storage forever.
            'edit_id' => ['nullable', 'image', 'max:5120'],
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

        /*
         * Private disk, never public storage. An ID is the most sensitive thing this app holds,
         * and putting it where a guessed URL would serve it is the whole failure mode. The old
         * file is dropped rather than orphaned when one replaces another.
         */
        if ($this->edit_id) {
            if ($participant->id_image) {
                Storage::disk('local')->delete($participant->id_image);
            }

            $participant->update([
                'id_image' => $this->edit_id->store('ids', 'local'),
                'id_uploaded_at' => now(),
            ]);
        }

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
            // Only a method this host actually offers. Free text here would let the roster show
            // a payment route the host never advertised.
            'method' => ['nullable', 'string', 'max:60', Rule::in(collect($this->payMethods)->pluck('label')->filter()->all())],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $service->submitPayment($this->reservation, [
            'method' => $this->method ?: null,
            'reference_no' => $this->reference_no,
            'proof_image' => $this->proof ? $this->proof->store('proofs', 'local') : null,
        ]);

        $this->reservation->refresh();

        // The sheet has done its job; leaving it open over a page that now says "waiting on your
        // host" is a dialog with nothing left to do in it.
        $this->payOpen = false;
        $this->reference_no = '';
        $this->proof = null;
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
