<div class="max-w-2xl mx-auto px-6 py-8" wire:poll.5s>
    <div class="flex items-center gap-3">
        <div>
            <h1 class="font-serif font-semibold text-2xl">{{ $departure->trip->title }}</h1>
            <p class="text-ink/65 text-sm">{{ $departure->starts_at->format('M j') }} · trip chat</p>
        </div>
        <a href="{{ route('trips.show', $departure->trip) }}" class="ml-auto text-sm text-ink/65">‹ Trip</a>
    </div>

    @if($pinned)
        <div class="mt-5 rounded-xl bg-amber/10 border border-amber/30 px-4 py-3 text-sm">
            <div class="text-xs font-bold uppercase tracking-wide text-amber-deep mb-1">📌 Pinned by host</div>
            {{ $pinned->body }}
        </div>
    @endif

    <div class="mt-6 space-y-3" dusk="messages">
        @forelse($messages as $m)
            @php($mine = $m->user_id === auth()->id())
            @php($fromHost = $m->user_id === $departure->trip->host_id)
            <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[80%]">
                    @unless($mine)<div class="text-xs text-ink/65 mb-0.5 ml-1">{{ $m->user->name }}@if($fromHost) · Host @endif</div>@endunless
                    <div class="rounded-2xl px-4 py-2.5 text-sm
                        {{ $mine ? 'bg-moss text-cream' : ($fromHost ? 'bg-pine text-cream' : 'bg-surface border border-ink/10') }}">
                        {{ $m->body }}
                    </div>
                    @if($this->isHost() && ! $m->pinned)
                        <button wire:click="pin({{ $m->id }})" class="text-xs text-ink/65 mt-1 ml-1">Pin</button>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-ink/65 text-center py-8">No messages yet. Say hello 👋</p>
        @endforelse
    </div>

    @if($readOnly)
        <p class="mt-6 text-center text-sm text-ink/65">This chat is closed — the trip ended over a week ago.</p>
    @else
        <form wire:submit="send" class="mt-6 flex gap-2">
            <input wire:model="body" dusk="chat-input" class="flex-1 rounded-full border border-ink/15 px-5 py-3" placeholder="Message the group…">
            <button dusk="chat-send" class="w-12 h-12 rounded-full bg-pine text-cream grid place-items-center">↑</button>
        </form>
    @endif
</div>
