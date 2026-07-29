<?php

namespace App\Livewire\Chat;

use App\Models\Message;
use App\Models\TripDeparture;
use App\Services\ChatGate;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Room extends Component
{
    public TripDeparture $departure;
    public string $body = '';

    public function mount(TripDeparture $departure, ChatGate $gate): void
    {
        $departure->load('trip');
        abort_unless($gate->canAccess(Auth::user(), $departure), 403);
        $this->departure = $departure;
    }

    public function send(ChatGate $gate): void
    {
        abort_if($gate->isReadOnly($this->departure), 403);
        $this->validate(['body' => ['required', 'string', 'max:2000']]);

        Message::create([
            'trip_departure_id' => $this->departure->id,
            'user_id' => Auth::id(),
            'body' => $this->body,
        ]);

        $this->body = '';
    }

    public function pin(int $messageId): void
    {
        abort_unless($this->isHost(), 403);
        $msg = Message::where('trip_departure_id', $this->departure->id)->findOrFail($messageId);
        // one pinned message at a time
        Message::where('trip_departure_id', $this->departure->id)->update(['pinned' => false]);
        $msg->update(['pinned' => true]);
    }

    public function isHost(): bool
    {
        return $this->departure->trip->host_id === Auth::id();
    }

    public function render()
    {
        $pinned = Message::where('trip_departure_id', $this->departure->id)
            ->where('pinned', true)->with('user')->latest()->first();

        $messages = Message::where('trip_departure_id', $this->departure->id)
            ->with('user')->orderBy('created_at')->get();

        return view('livewire.chat.room', [
            'messages' => $messages,
            'pinned' => $pinned,
            'readOnly' => app(ChatGate::class)->isReadOnly($this->departure),
        ])->layout('components.layouts.site');
    }
}
