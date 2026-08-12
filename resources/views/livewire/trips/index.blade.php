<div class="max-w-6xl mx-auto px-6 py-10">
    <div class="max-w-2xl">
        <h1 class="font-serif font-semibold text-4xl">Explore trips</h1>
        <p class="text-ink/70 mt-2">Group departures led by hosts who know the route. Filter to your pace and pick a date.</p>
    </div>

    <div class="sticky top-[68px] z-10 -mx-6 px-6 py-4 mt-6 bg-canvas/85 backdrop-blur border-b border-ink/5">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[220px]">
                <svg viewBox="0 0 24 24" class="w-5 h-5 text-ink/45 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="11" cy="11" r="6.6"/><path d="M15.8 15.8l4.7 4.7"/></svg>
                <input wire:model.live.debounce.300ms="search" placeholder="Search destinations"
                       class="w-full rounded-full border border-ink/12 bg-surface pl-11 pr-5 py-3 focus:border-moss focus:ring-moss">
            </div>
            <select wire:model.live="category" aria-label="Filter by trip type" class="rounded-full border border-ink/12 bg-surface px-4 py-3 text-sm capitalize focus:border-moss focus:ring-moss">
                <option value="">All trip types</option>
                @foreach($categories as $cat)<option value="{{ $cat }}" class="capitalize">{{ ucfirst($cat) }}</option>@endforeach
            </select>
            <select wire:model.live="country" aria-label="Filter by country" class="rounded-full border border-ink/12 bg-surface px-4 py-3 text-sm focus:border-moss focus:ring-moss">
                <option value="">All countries</option>
                @foreach($countries as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
            </select>
            <select wire:model.live="difficulty" aria-label="Filter by difficulty" class="rounded-full border border-ink/12 bg-surface px-4 py-3 text-sm focus:border-moss focus:ring-moss">
                <option value="">Any difficulty</option>
                <option value="easy">Easy</option>
                <option value="challenging">Challenging</option>
                <option value="hard">Hard</option>
            </select>
        </div>
    </div>

    <p class="text-sm text-ink/65 mt-6 mb-4">
        <span wire:loading.remove wire:target="search,category,country,difficulty">{{ $trips->total() }} {{ Str::plural('trip', $trips->total()) }}</span>
        <span wire:loading wire:target="search,category,country,difficulty" class="inline-flex items-center gap-2 text-ink/50">
            <svg viewBox="0 0 24 24" class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9" stroke-linecap="round"/></svg>
            Updating results…
        </span>
    </p>

    <div class="grid sm:grid-cols-2 gap-8 transition-opacity" wire:loading.class="opacity-40" wire:target="search,category,country,difficulty">
        @forelse($trips as $trip)
            <x-trip-card :$trip />
        @empty
            <div class="col-span-full text-center py-16">
                <p class="font-serif text-xl text-ink/70">No trips match yet.</p>
                <p class="text-ink/65 mt-1">Try widening your filters.</p>
            </div>
        @endforelse

        @if($trips->hasPages())
            <div class="mt-8">{{ $trips->links() }}</div>
        @endif
    </div>
</div>
