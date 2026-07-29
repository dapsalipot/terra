<div>
    <div class="flex flex-wrap items-end justify-between gap-4 mb-7">
        <div>
            <h1 class="font-serif font-semibold text-3xl mb-1">My trips</h1>
            <p class="text-ink/65">Each date is its own schedule, so you can price and run them differently.</p>
        </div>
        <a href="{{ route('host.trips.create') }}" wire:navigate
           class="inline-flex items-center gap-2 rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors">
            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            New trip
        </a>
    </div>

    <div class="flex flex-wrap gap-2 mb-6" role="group" aria-label="Filter trips">
        @foreach(['upcoming' => 'Upcoming', 'past' => 'Past', 'all' => 'All'] as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')" aria-pressed="{{ $filter === $key ? 'true' : 'false' }}"
                    dusk="filter-{{ $key }}"
                    class="rounded-full px-5 min-h-[44px] text-sm font-semibold border transition-colors
                           {{ $filter === $key ? 'bg-moss text-cream border-moss' : 'border-ink/15 hover:border-ink/30' }}">
                {{ $label }} <span class="opacity-70">{{ $counts[$key] }}</span>
            </button>
        @endforeach
    </div>

    @forelse($groups as $group)
        @php($first = $group->first())
        <section class="glass-card overflow-hidden mb-4">
            <header class="flex items-start gap-4 px-5 py-4 border-b border-ink/8 bg-cream/50">
                <img src="{{ $first->cover_url }}" alt="" class="w-16 h-16 rounded-xl object-cover shrink-0">
                <div class="flex-1 min-w-0">
                    <h2 class="font-serif font-semibold text-xl truncate">{{ $first->title }}</h2>
                    <p class="text-sm text-ink/65 truncate">
                        {{ $first->destinations->map(fn ($d) => $d->place?->name)->filter()->join(' · ') ?: $first->place?->name }}
                    </p>
                </div>
                <span class="shrink-0 rounded-full bg-ink/[.06] px-3 py-1.5 text-xs font-bold">
                    {{ $group->count() }} {{ \Illuminate\Support\Str::plural('schedule', $group->count()) }}
                </span>
            </header>

            <ul class="divide-y divide-ink/8">
                @foreach($group as $trip)
                    @php($dep = $trip->departure)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-3 px-5 py-4">
                        <div class="min-w-[11rem]">
                            <p class="font-semibold">
                                {{ $dep?->starts_at?->format('D j M Y') ?? 'No date' }}
                                @if($dep?->ends_at) <span class="font-normal text-ink/65">→ {{ $dep->ends_at->format('j M') }}</span>@endif
                            </p>
                            <p class="text-sm text-ink/65">
                                {{ $trip->host->hostProfile?->currency ?? 'PHP' }} {{ number_format((float) $trip->price) }}
                                @if($dep) · {{ $dep->capacity }} places @endif
                            </p>
                        </div>

                        @if($dep)
                            <span class="rounded-full px-3 py-1 text-xs font-bold
                                         {{ $dep->starts_at->isPast() ? 'bg-ink/[.06] text-ink/70' : 'bg-moss/12 text-pine' }}">
                                {{ $dep->starts_at->isPast() ? 'Ran' : $dep->starts_at->diffForHumans() }}
                            </span>
                        @endif

                        <div class="flex flex-wrap items-center gap-2 ml-auto">
                            <a href="{{ route('trips.show', $trip) }}" wire:navigate
                               class="rounded-full border border-ink/15 px-4 min-h-[40px] inline-flex items-center text-sm font-semibold hover:border-ink/30 transition-colors">View</a>
                            <a href="{{ route('host.trips.edit', $trip) }}" wire:navigate dusk="edit-{{ $trip->id }}"
                               class="rounded-full border border-ink/15 px-4 min-h-[40px] inline-flex items-center gap-1.5 text-sm font-semibold hover:border-moss transition-colors">
                                <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 text-moss" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/></svg>
                                Edit
                            </a>
                            @if($dep)
                                <a href="{{ route('host.roster', $dep) }}" wire:navigate
                                   class="rounded-full border border-ink/15 px-4 min-h-[40px] inline-flex items-center text-sm font-semibold hover:border-ink/30 transition-colors">Roster</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="px-5 py-3 border-t border-ink/8 bg-cream/30">
                <a href="{{ route('host.trips.duplicate', $first) }}" wire:navigate
                   class="text-sm font-semibold text-moss hover:underline">＋ Add another schedule</a>
            </div>
        </section>
    @empty
        <div class="rounded-2xl border-2 border-dashed border-ink/15 px-6 py-12 text-center">
            <p class="font-semibold mb-1">Nothing {{ $filter === 'all' ? 'here' : $filter }} yet</p>
            <p class="text-ink/65 text-sm mb-5">Publish a trip and every date you add becomes its own schedule.</p>
            <a href="{{ route('host.trips.create') }}" wire:navigate
               class="inline-flex items-center rounded-full bg-pine text-cream px-6 min-h-[48px] font-semibold hover:bg-pine/90 transition-colors">New trip</a>
        </div>
    @endforelse
</div>
