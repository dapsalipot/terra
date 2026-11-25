@php($p = $user->hostProfile)
@php($accent = $p?->accent_hex ?? '#5E6B44')
@php($accentSoft = $p?->accent_soft ?? '#EDF0E6')
@php($accentText = $p?->accent_text ?? '#5E6B44')

<div style="--accent: {{ $accent }}; --accent-soft: {{ $accentSoft }}; --accent-text: {{ $accentText }}">
    {{-- ══════════════════ COVER ══════════════════ --}}
    <div class="relative h-56 md:h-80 overflow-hidden">
        @if($p?->cover_url)
            <img src="{{ $p->cover_url }}" alt="" aria-hidden="true" class="absolute inset-0 w-full h-full object-cover">
        @else
            <div class="absolute inset-0" style="background:linear-gradient(120deg, var(--accent) 0%, var(--accent-soft) 100%)"></div>
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-ink/35 via-transparent to-transparent"></div>
        <div class="grain-overlay" style="opacity:.22" aria-hidden="true"></div>
    </div>

    <div class="backdrop backdrop-{{ \App\Support\Backdrops::key($p?->backdrop) }}">
    {{-- ══════════════════ IDENTITY ══════════════════
         Only the avatar overlaps the cover; all text sits on the canvas so it is
         always legible regardless of what the host uploaded behind it. --}}
    <div class="max-w-5xl mx-auto px-6">
        <div class="flex flex-wrap items-end gap-5 -mt-16 md:-mt-20 relative">
            <span class="relative grid place-items-center w-32 h-32 md:w-40 md:h-40 rounded-[2rem] ring-[6px] ring-canvas overflow-hidden bg-pine text-cream tracking-tight font-semibold text-5xl shrink-0 shadow-[0_18px_40px_-18px_rgba(31,28,21,.6)]">
                @if($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}" class="absolute inset-0 w-full h-full object-cover">
                @else
                    {{ $user->initials }}
                @endif
            </span>

            {{-- actions sit on the cover side, clear of the name --}}
            <div class="ml-auto mb-4 flex items-center gap-2.5">
                @if($this->isOwner)
                    <a href="{{ route('host.profile') }}"
                       class="inline-flex items-center gap-2 rounded-full bg-pine text-cream px-6 py-3 font-semibold text-sm shadow-sm hover:bg-pine/90 transition-colors">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17z"/></svg>
                        Edit page
                    </a>
                    <a href="{{ route('host.trips.create') }}"
                       class="inline-flex items-center gap-2 rounded-full border border-ink/20 bg-surface px-6 py-3 font-semibold text-sm hover:bg-ink/5 transition-colors">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5.5v13M5.5 12h13"/></svg>
                        New trip
                    </a>
                @else
                    @if($this->isFollowing)
                        <button wire:click="unfollow" dusk="follow-toggle"
                                class="inline-flex items-center gap-2 rounded-full border-2 border-ink/20 bg-surface px-7 py-3 font-semibold text-sm hover:border-ink/35 transition-colors">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                            Following
                        </button>
                    @else
                        <button wire:click="follow" dusk="follow-toggle"
                                class="inline-flex items-center gap-2 rounded-full bg-pine text-cream px-8 py-3 font-semibold text-sm shadow-[0_12px_28px_-14px_rgba(31,28,21,.8)] hover:bg-pine/90 hover:-translate-y-0.5 transition-all">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5.5v13M5.5 12h13"/></svg>
                            Follow
                        </button>
                    @endif
                @endif
            </div>
        </div>

        {{-- name block, fully on canvas --}}
        <div class="mt-5">
            <h1 class="flex items-center gap-2.5 tracking-tight font-semibold text-4xl md:text-5xl leading-none">
                {{ $user->name }}
                @if($p?->isVerified())
                    <svg viewBox="0 0 24 24" class="h-7 w-7 shrink-0 md:h-8 md:w-8" style="color: var(--accent-text)" role="img" aria-label="Verified host"><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M7.5 12.5l3 3 6-6.5" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @endif
            </h1>
            <p class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-ink/70 mt-2.5">
                <span class="font-semibold">&commat;{{ $user->handle }}</span>
                @if($p?->location)
                    <span class="text-ink/30" aria-hidden="true">·</span>
                    <span class="inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s7-6.4 7-11a7 7 0 1 0-14 0c0 4.6 7 11 7 11z"/><circle cx="12" cy="10" r="2.4"/></svg>
                        {{ $p->location }}
                    </span>
                @endif
            </p>

            @if($p?->tagline)
                <p class="tracking-tight text-2xl mt-4" style="color: var(--accent-text)">{{ $p->tagline }}</p>
            @endif
            @if($p?->vibeLabels())
                <div class="mt-4 flex flex-wrap gap-2" aria-label="How {{ $user->name }}'s trips feel">
                    @foreach($p->vibeLabels() as $label)
                        <span class="rounded-full px-3.5 py-1.5 text-sm font-semibold" style="background: var(--accent-soft); color: var(--accent-text)">{{ $label }}</span>
                    @endforeach
                </div>
            @endif
            @if($p?->bio)
                <p class="text-ink/75 leading-relaxed mt-4 max-w-2xl">{{ $p->bio }}</p>
            @endif
            @if($p?->highlightLines())
                <ul class="mt-5 max-w-2xl space-y-2" aria-label="Why join {{ $user->name }}" dusk="highlights">
                    @foreach($p->highlightLines() as $line)
                        <li class="flex items-start gap-2.5 text-ink/85">
                            <svg viewBox="0 0 24 24" class="mt-0.5 h-5 w-5 shrink-0" style="color: var(--accent-text)" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.6l4.6 4.6L19 7.4"/></svg>
                            {{ $line }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- stats --}}
        <dl class="flex flex-wrap gap-x-10 gap-y-4 mt-7">
            @foreach([
                [$p?->trips_count ?? 0, 'Trips led'],
                [$p?->guests_count ?? 0, 'Guests'],
                [$p?->followers_count ?? 0, 'Followers'],
                [$reviews->count(), 'Reviews'],
                [$p?->rating ?? '—', 'Rating'],
            ] as [$value, $label])
                <div>
                    <dd class="tracking-tight font-semibold text-3xl leading-none">{{ $value }}</dd>
                    <dt class="marker text-[11px] font-semibold text-ink/65 mt-2">{{ $label }}</dt>
                </div>
            @endforeach
        </dl>

        @if($p?->links && array_filter($p->links))
            <div class="flex flex-wrap gap-2 mt-6">
                @foreach(array_filter($p->links) as $kind => $value)
                    @php($href = \App\Support\SocialLinks::url($kind, $value))
                    @continue(! $href)
                    <a href="{{ $href }}" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-xs font-semibold hover:brightness-95 hover:-translate-y-0.5 transition-all"
                       style="background: var(--accent-soft); color: var(--accent-text)">
                        {{ ucfirst($kind) }} · {{ \App\Support\SocialLinks::label($kind, $value) }}
                        <svg viewBox="0 0 24 24" class="w-3 h-3 opacity-70" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17L17 7M8 7h9v9"/></svg>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- ══════════════════ TABS ══════════════════ --}}
        {{-- Tighter padding on phones so all four fit; scroll is the fallback, not the default. --}}
        <div class="flex gap-1 mt-9 border-b border-ink/10 overflow-x-auto sm:overflow-x-visible" role="tablist">
            @php($tabs = ['trips' => 'Trips', 'wall' => 'Wall', 'reviews' => 'Reviews', 'about' => 'About'])
            @foreach($tabs as $key => $label)
                <button wire:click="setTab('{{ $key }}')" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                        dusk="tab-{{ $key }}"
                        class="relative px-3 sm:px-5 py-3.5 min-h-[44px] text-sm font-semibold whitespace-nowrap transition-colors {{ $tab === $key ? 'text-ink' : 'text-ink/65 hover:text-ink' }}">
                    {{ $label }}
                    @if($key === 'reviews' && $reviews->isNotEmpty())
                        <span class="ml-1 text-ink/65 font-normal">{{ $reviews->count() }}</span>
                    @endif
                    @if($tab === $key)
                        <span class="absolute inset-x-3 -bottom-px h-0.5 rounded-full" style="background: var(--accent)"></span>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- ══════════════════ PANELS ══════════════════ --}}
    <div class="max-w-5xl mx-auto px-6 py-8 min-h-[40vh]">

        {{-- ───── TRIPS ───── --}}
        @if($tab === 'trips')
            @if($upcoming->isNotEmpty())
                <section class="mb-10" aria-labelledby="upcoming-heading" dusk="upcoming">
                    <h2 id="upcoming-heading" class="text-xl font-semibold tracking-tight">Next departures</h2>
                    <ul class="mt-4 divide-y divide-ink/10 overflow-hidden rounded-2xl border border-ink/10 bg-surface">
                        @foreach($upcoming as $dep)
                            @php($left = $dep->spotsLeft())
                            <li>
                                <a href="{{ route('trips.show', $dep->trip) }}" class="flex items-center gap-4 px-4 py-3.5 transition-colors hover:bg-ink/[.03]">
                                    <span class="w-12 shrink-0 text-center leading-none">
                                        <span class="block text-xl font-semibold tabular-nums">{{ $dep->starts_at->format('j') }}</span>
                                        <span class="mt-1 block text-[11px] font-semibold uppercase tracking-wide text-ink/65">{{ $dep->starts_at->format('M') }}</span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-semibold">{{ $dep->trip->title }}</span>
                                        <span class="block truncate text-sm text-ink/65">{{ $dep->trip->place?->name }}@if($dep->trip->place?->country), {{ $dep->trip->place->country }}@endif</span>
                                    </span>
                                    <span class="shrink-0 text-right text-sm">
                                        <span class="block font-semibold tabular-nums">{{ $user->hostProfile?->currency ?? 'PHP' }} {{ number_format($dep->trip->current_price) }}</span>
                                        @if($left !== null)<span class="block {{ $left <= 4 ? 'font-semibold text-terra-deep' : 'text-ink/65' }}">{{ $left === 0 ? 'Full' : $left.' left' }}</span>@endif
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($trips->isNotEmpty())
                <h2 class="mb-4 text-xl font-semibold tracking-tight">{{ $upcoming->isNotEmpty() ? 'All trips' : 'Trips' }}</h2>
                <div class="grid sm:grid-cols-2 gap-8">
                    @foreach($trips as $trip)<x-trip-card :$trip />@endforeach
                </div>
            @else
                <div class="text-center py-16">
                    <p class="tracking-tight text-xl text-ink/70">No published trips yet.</p>
                    @if($this->isOwner)
                        <a href="{{ route('host.trips.create') }}" class="inline-block mt-4 rounded-full bg-pine text-cream px-6 py-3 font-semibold text-sm">Post your first trip</a>
                    @else
                        <p class="text-ink/65 mt-1">Follow to hear when {{ $user->name }} posts one.</p>
                    @endif
                </div>
            @endif
        @endif

        {{-- ───── WALL ───── --}}
        @if($tab === 'wall')
            <div class="max-w-2xl mx-auto">
                @if($this->isOwner)
                    <div class="rounded-2xl border border-ink/10 bg-surface p-5 mb-6 shadow-sm">
                        <textarea wire:model="postBody" dusk="post-body" rows="2"
                                  class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss"
                                  placeholder="Share an update with your followers…"></textarea>

                        @if($postImages)
                            <div class="grid grid-cols-4 gap-2 mt-3">
                                @foreach($postImages as $i => $img)
                                    <div class="relative group" wire:key="staged-{{ $i }}">
                                        <img src="{{ $img->temporaryUrl() }}" alt="" class="aspect-square w-full object-cover rounded-lg">
                                        <button type="button" wire:click="removeStagedImage({{ $i }})" aria-label="Remove image"
                                                class="absolute top-1 right-1 grid place-items-center w-6 h-6 rounded-full bg-ink/70 text-cream opacity-0 group-hover:opacity-100 transition-opacity">
                                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @error('postBody')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                        @error('postImages.*')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror

                        <div class="flex items-center gap-3 mt-3">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-ink/65 hover:text-ink cursor-pointer">
                                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2.5"/><circle cx="8.5" cy="10" r="1.6"/><path d="M21 16l-5-5-6.5 8"/></svg>
                                Photos
                                <input wire:model="postImages" type="file" accept="image/*" multiple class="hidden">
                            </label>
                            <span wire:loading wire:target="postImages" class="text-xs text-ink/65">Uploading…</span>
                            <button wire:click="publishPost" dusk="post-publish" class="ml-auto rounded-full bg-pine text-cream px-6 py-2.5 font-semibold text-sm hover:bg-pine/90 transition-colors">Post</button>
                        </div>
                    </div>
                @endif

                <div class="space-y-5" dusk="wall">
                    @forelse($posts as $post)
                        <article class="rounded-2xl border border-ink/8 bg-surface overflow-hidden shadow-sm">
                            <div class="flex items-center gap-3 p-5 pb-3">
                                <span class="relative grid place-items-center w-10 h-10 rounded-full overflow-hidden bg-pine text-cream font-semibold text-sm shrink-0">
                                    @if($user->avatar_url)
                                        <img src="{{ $user->avatar_url }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                                    @else{{ $user->initials }}@endif
                                </span>
                                <div class="min-w-0">
                                    <div class="font-semibold text-sm leading-tight">{{ $user->name }}</div>
                                    <div class="text-xs text-ink/65">{{ $post->created_at->diffForHumans() }}</div>
                                </div>
                                @if($post->isRecap())
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold" style="background: var(--accent-soft); color: var(--accent-text)">Recap</span>
                                @endif
                                @if($this->isOwner)
                                    <x-confirm-action
                                        action="deletePost({{ $post->id }})"
                                        title="Delete this post?"
                                        body="It disappears from your wall for everyone who follows you. This cannot be undone."
                                        confirm-label="Yes, delete it"
                                        cancel-label="Keep it"
                                        tone="terra" icon="trash"
                                        dusk="delete-post-{{ $post->id }}"
                                        aria-label="Delete post" class="ml-auto text-ink/65 hover:text-terra">
                                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                    </x-confirm-action>
                                @endif
                            </div>

                            <p class="px-5 pb-4 text-ink/85 leading-relaxed">{{ $post->body }}</p>

                            @if($post->images->isNotEmpty())
                                <x-carousel :images="$post->images->map(fn($i) => ['url' => $i->url, 'alt' => ''])" ratio="aspect-[4/3]" />
                            @endif

                            @if($post->trip)
                                <a href="{{ route('trips.show', $post->trip) }}"
                                   class="flex items-center gap-2 px-5 py-3.5 text-sm font-semibold border-t border-ink/6 hover:bg-ink/[.02] transition-colors"
                                   style="color: var(--accent-text)">
                                    {{ $post->trip->title }} →
                                </a>
                            @endif
                        </article>
                    @empty
                        <div class="text-center py-16">
                            <p class="tracking-tight text-xl text-ink/70">Nothing posted yet.</p>
                            <p class="text-ink/65 mt-1">{{ $this->isOwner ? 'Share a photo or an update to start your wall.' : 'Check back after their next trip.' }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        @endif

        {{-- ───── REVIEWS ───── --}}
        @if($tab === 'reviews')
            <div class="max-w-2xl mx-auto">
                @if($reviews->isNotEmpty())
                    <div class="flex items-baseline gap-3 mb-6">
                        <span class="tracking-tight font-semibold text-4xl">{{ $p?->rating ?? '—' }}</span>
                        <span class="text-amber text-lg tracking-widest">{{ str_repeat('★', (int) round($p?->rating ?? 0)) }}</span>
                        <span class="text-ink/65 text-sm">{{ $reviews->count() }} {{ Str::plural('review', $reviews->count()) }}</span>
                    </div>

                    <div class="space-y-4">
                        @foreach($reviews as $review)
                            <article class="rounded-2xl border border-ink/8 bg-surface p-5">
                                <div class="flex items-center gap-3">
                                    <span class="grid place-items-center w-9 h-9 rounded-full bg-moss/15 text-pine font-semibold text-sm shrink-0">
                                        {{ $review->joiner->initials }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-sm">{{ $review->joiner->name }}</div>
                                        <div class="text-xs text-ink/65">{{ $review->created_at->diffForHumans() }}</div>
                                    </div>
                                    <span class="ml-auto text-amber text-sm tracking-widest">{{ str_repeat('★', $review->rating) }}</span>
                                </div>
                                <p class="mt-3 text-ink/80 leading-relaxed">{{ $review->body }}</p>
                                @if($review->reviewable instanceof \App\Models\Trip)
                                    <a href="{{ route('trips.show', $review->reviewable) }}" class="inline-block mt-3 text-xs font-semibold" style="color: var(--accent-text)">
                                        on {{ $review->reviewable->title }} →
                                    </a>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-16">
                        <p class="tracking-tight text-xl text-ink/70">No reviews yet.</p>
                        <p class="text-ink/65 mt-1">Reviews appear here once joiners finish a trip.</p>
                    </div>
                @endif
            </div>
        @endif

        {{-- ───── ABOUT ───── --}}
        @if($tab === 'about')
            <div class="max-w-2xl grid gap-5">
                <section class="rounded-2xl border border-ink/10 bg-surface p-6">
                    <h2 class="font-semibold text-lg mb-2">About {{ $user->name }}</h2>
                    <p class="text-ink/75 leading-relaxed">{{ $p?->bio ?: 'This host hasn’t written a bio yet.' }}</p>
                    @if($p?->location)
                        <p class="text-sm text-ink/65 mt-4">Based in <b class="text-ink/80">{{ $p->location }}</b></p>
                    @endif
                </section>

                @if($p?->payment_methods)
                    <section class="rounded-2xl border border-ink/10 bg-surface p-6">
                        <h2 class="font-semibold text-lg mb-1">How you pay</h2>
                        <p class="text-sm text-ink/65 mb-4">You pay {{ $user->name }} directly — Tarana never holds your money.</p>
                        <div class="grid sm:grid-cols-2 gap-3">
                            @foreach($p->payment_methods as $m)
                                <div class="flex items-center gap-3 rounded-2xl border border-ink/12 p-3">
                                    @if($m['qr'] ?? null)
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($m['qr']) }}"
                                             alt="{{ $m['label'] ?? '' }} QR code"
                                             class="w-20 h-20 rounded-xl object-cover shrink-0 border border-ink/8">
                                    @endif
                                    <div class="min-w-0">
                                        <div class="font-semibold text-sm">{{ $m['label'] ?? '' }}</div>
                                        <div class="text-sm text-ink/65 break-words">{{ $m['detail'] ?? '' }}</div>
                                        @if($m['qr'] ?? null)
                                            <div class="text-[11px] text-ink/65 mt-1">Scan to pay</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        @endif
    </div>
    </div>
</div>
