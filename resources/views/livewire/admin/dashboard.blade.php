<div class="w-full px-5 sm:px-8 2xl:px-12 py-8">
    <div class="mb-7">
        <h1 class="tracking-tight font-semibold text-3xl">Admin</h1>
        <p class="text-ink/65">Moderation. Everything here is reversible — nothing is deleted.</p>
    </div>

    {{-- The shape of the platform, in six numbers. --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-8">
        @foreach([
            ['Trips live', $counts['trips'], 'ink'],
            ['Hosts', $counts['hosts'], 'ink'],
            ['Joiners', $counts['joiners'], 'ink'],
            ['Bookings', $counts['bookings'], 'ink'],
            ['Suspended', $counts['suspended'], $counts['suspended'] ? 'terra' : 'ink'],
            ['Hidden reviews', $counts['hidden'], $counts['hidden'] ? 'amber' : 'ink'],
        ] as [$label, $value, $tone])
            <div class="rounded-2xl border border-ink/10 bg-surface p-4">
                <p class="text-[10px] uppercase tracking-[0.09em] text-ink/65 font-semibold">{{ $label }}</p>
                <p class="tracking-tight font-bold text-2xl tabular-nums mt-1 {{ $tone === 'terra' ? 'text-terra' : ($tone === 'amber' ? 'text-amber' : '') }}">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-5">
        <div class="flex flex-wrap gap-2" role="group" aria-label="Section">
            @foreach(\App\Livewire\Admin\Dashboard::TABS as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')" dusk="tab-{{ $key }}"
                        aria-pressed="{{ $tab === $key ? 'true' : 'false' }}"
                        class="rounded-full px-4 min-h-[44px] text-sm font-semibold border transition-colors
                               {{ $tab === $key ? 'bg-pine text-cream border-pine' : 'border-ink/15 hover:border-ink/35' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <label class="relative flex-1 min-w-[14rem]">
            <span class="sr-only">Search</span>
            <input wire:model.live.debounce.300ms="search" type="search" dusk="admin-search"
                   placeholder="Search {{ strtolower(\App\Livewire\Admin\Dashboard::TABS[$tab]) }}"
                   class="w-full rounded-full border border-ink/15 bg-surface px-4 min-h-[44px] focus:border-moss focus:ring-2 focus:ring-moss/25 transition-colors">
        </label>
    </div>

    <div class="rounded-2xl border border-ink/10 bg-surface overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">{{ \App\Livewire\Admin\Dashboard::TABS[$tab] }}</caption>
                <tbody>
                    @if($tab === 'trips')
                        @forelse($trips as $t)
                            <tr class="border-t border-ink/8 first:border-t-0" dusk="admin-trip-{{ $t->id }}">
                                <th scope="row" class="px-4 py-3.5 text-left font-normal">
                                    <a href="{{ route('trips.show', $t) }}" class="font-semibold hover:underline">{{ $t->title }}</a>
                                    <span class="block text-xs text-ink/65 mt-0.5">{{ $t->host?->name }}</span>
                                </th>
                                <td class="px-4 py-3.5">
                                    <x-badge :tone="$t->status === 'published' ? 'moss' : ($t->status === 'archived' ? 'terra' : 'ink')">
                                        {{ ucfirst($t->status) }}
                                    </x-badge>
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    @if($t->status === 'published')
                                        <x-confirm-action
                                            action="unpublish({{ $t->id }})"
                                            title="Take this trip out of discovery?"
                                            body="{{ $t->title }} stops appearing anywhere public. Existing bookings and their pages are untouched, and you can put it back."
                                            confirm-label="Yes, unpublish"
                                            cancel-label="Leave it"
                                            tone="terra" icon="warning"
                                            dusk="unpublish-{{ $t->id }}"
                                            class="rounded-lg border border-ink/15 px-3 min-h-[36px] inline-flex items-center text-[13px] font-semibold text-terra hover:text-terra-deep hover:border-terra/30 transition-colors">Unpublish</x-confirm-action>
                                    @elseif($t->status === 'archived')
                                        <button wire:click="republish({{ $t->id }})" dusk="republish-{{ $t->id }}"
                                                class="rounded-lg border border-ink/15 px-3 min-h-[36px] text-[13px] font-semibold hover:border-moss transition-colors">Republish</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-10 text-center text-ink/65">No trips match that.</td></tr>
                        @endforelse

                    @elseif($tab === 'hosts')
                        @forelse($hosts as $h)
                            <tr class="border-t border-ink/8 first:border-t-0" dusk="admin-host-{{ $h->id }}">
                                <th scope="row" class="px-4 py-3.5 text-left font-normal">
                                    <span class="font-semibold">{{ $h->name }}</span>
                                    <span class="block text-xs text-ink/65 mt-0.5">{{ $h->email }} · {{ $h->trips_count }} {{ \Illuminate\Support\Str::plural('trip', $h->trips_count) }}</span>
                                </th>
                                <td class="px-4 py-3.5">
                                    @if($h->isSuspended())
                                        <x-badge tone="terra" dot>Suspended</x-badge>
                                    @else
                                        <x-badge tone="moss">Active</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    @if($h->isSuspended())
                                        <button wire:click="unsuspend({{ $h->id }})" dusk="unsuspend-{{ $h->id }}"
                                                class="rounded-lg border border-ink/15 px-3 min-h-[36px] text-[13px] font-semibold hover:border-moss transition-colors">Lift suspension</button>
                                    @else
                                        <x-confirm-action
                                            action="suspend({{ $h->id }})"
                                            title="Suspend {{ $h->name }}?"
                                            body="Their trips stop appearing publicly. Bookings already made stay reachable for the joiners on them — suspension is not deletion."
                                            confirm-label="Yes, suspend"
                                            cancel-label="Not yet"
                                            tone="terra" icon="warning"
                                            dusk="suspend-{{ $h->id }}"
                                            class="rounded-lg border border-ink/15 px-3 min-h-[36px] inline-flex items-center text-[13px] font-semibold text-terra hover:text-terra-deep hover:border-terra/30 transition-colors">Suspend</x-confirm-action>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-10 text-center text-ink/65">No hosts match that.</td></tr>
                        @endforelse

                    @else
                        @forelse($reviews as $rev)
                            <tr class="border-t border-ink/8 first:border-t-0" dusk="admin-review-{{ $rev->id }}">
                                <th scope="row" class="px-4 py-3.5 text-left font-normal max-w-md">
                                    <span class="block text-ink/80">{{ \Illuminate\Support\Str::limit($rev->body ?: '(no words, rating only)', 140) }}</span>
                                    <span class="block text-xs text-ink/65 mt-1">{{ $rev->joiner?->name ?? 'Unknown' }} · {{ $rev->rating }}★ · {{ $rev->created_at?->format('j M Y') }}</span>
                                </th>
                                <td class="px-4 py-3.5">
                                    @if($rev->hidden_at)
                                        <x-badge tone="amber" dot>Hidden</x-badge>
                                    @else
                                        <x-badge tone="moss">Visible</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    @if($rev->hidden_at)
                                        <button wire:click="unhideReview({{ $rev->id }})" dusk="unhide-{{ $rev->id }}"
                                                class="rounded-lg border border-ink/15 px-3 min-h-[36px] text-[13px] font-semibold hover:border-moss transition-colors">Restore</button>
                                    @else
                                        <x-confirm-action
                                            action="hideReview({{ $rev->id }})"
                                            title="Hide this review?"
                                            body="It stops showing publicly and stops counting toward the rating. The words are kept, and you can restore it."
                                            confirm-label="Yes, hide it"
                                            cancel-label="Leave it"
                                            tone="terra" icon="warning"
                                            dusk="hide-{{ $rev->id }}"
                                            class="rounded-lg border border-ink/15 px-3 min-h-[36px] inline-flex items-center text-[13px] font-semibold text-terra hover:text-terra-deep hover:border-terra/30 transition-colors">Hide</x-confirm-action>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-4 py-10 text-center text-ink/65">No reviews match that.</td></tr>
                        @endforelse
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    @php($rows = $tab === 'trips' ? $trips : ($tab === 'hosts' ? $hosts : $reviews))
    @if($rows->hasPages())
        <div class="mt-6">{{ $rows->links() }}</div>
    @endif
</div>
