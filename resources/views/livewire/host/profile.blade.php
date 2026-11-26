<div class="max-w-6xl" x-data>
    <div class="flex items-end justify-between mb-6">
        <div>
            <h1 class="tracking-tight font-semibold text-3xl">Your page</h1>
            <p class="text-ink/65 mt-1 text-sm">Everything here is public at
                {{-- &commat; not a literal @, which Blade would read as the start of a directive --}}
                <a href="{{ $user->handle ? route('hosts.show', $user) : '#' }}" class="font-semibold underline decoration-ink/25">tarana/&commat;{{ $handle ?: 'your-handle' }}</a>.
            </p>
            {{-- The saved handle, not the one being typed: until it is saved there is no page at that address to share. --}}
            @if(filled($user->handle))
                <x-share-panel class="mt-3" :url="route('hosts.handle', $user)" :title="$user->name" :qr="route('host.profile.qr')" />
            @endif
        </div>
        @if($saved)
            <span class="rounded-full bg-moss/12 text-moss px-4 py-2 text-sm font-semibold">{{ $saved }}</span>
        @endif
    </div>

    <div class="items-start lg:grid lg:grid-cols-[minmax(0,1fr)_21rem] lg:gap-8">
    <div class="min-w-0">

    {{-- ══════════ LIVE PREVIEW ══════════ --}}
    <div class="rounded-3xl overflow-hidden border border-ink/10 bg-surface shadow-sm mb-8">
        <div class="relative h-40">
            @if($cover)
                <img src="{{ $cover->temporaryUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            @elseif($user->hostProfile?->cover_url)
                <img src="{{ $user->hostProfile->cover_url }}" alt="" class="absolute inset-0 w-full h-full object-cover">
            @else
                <div class="absolute inset-0" style="background:linear-gradient(120deg, {{ \App\Support\Accents::hex($accent) }} 0%, {{ \App\Support\Accents::soft($accent) }} 100%)"></div>
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-ink/45 to-transparent"></div>
        </div>
        {{-- `relative` matters: the cover's absolutely-positioned <img> would otherwise paint
             over this statically-positioned block and hide the name. --}}
        {{-- `relative` matters: the cover's absolutely-positioned <img> would otherwise paint
             over this block. Only the avatar overlaps the cover — all text sits below it, so it
             stays legible whatever the host uploads. --}}
        <div class="relative px-6 pb-6">
            <span class="relative -mt-10 grid place-items-center w-20 h-20 rounded-2xl ring-4 ring-surface overflow-hidden bg-pine text-cream tracking-tight font-semibold text-2xl shrink-0">
                @if($avatar)
                    <img src="{{ $avatar->temporaryUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                @elseif($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                @else
                    {{ strtoupper(mb_substr($name ?: 'H', 0, 1)) }}
                @endif
            </span>

            <div class="mt-3 min-w-0">
                <div class="tracking-tight font-semibold text-xl truncate">{{ $name ?: 'Your name' }}</div>
                <div class="text-sm text-ink/65 truncate">
                    &commat;{{ $handle ?: 'handle' }}@if($location) · {{ $location }}@endif
                </div>
            </div>
            @if($tagline)
                <p class="mt-3 text-lg" style="color:{{ \App\Support\Accents::hex($accent) }}">{{ $tagline }}</p>
            @endif
            @if($bio)<p class="mt-2 text-sm text-ink/70 leading-relaxed">{{ $bio }}</p>@endif
        </div>
    </div>

    <form wire:submit="save" class="space-y-8">
        {{-- ══════════ IDENTITY ══════════ --}}
        <section class="rounded-2xl border border-ink/10 bg-surface p-6">
            <h2 class="font-semibold text-lg mb-5">Identity</h2>
            <div class="grid sm:grid-cols-2 gap-5">
                <label class="block">
                    <span class="block text-sm font-semibold text-ink/70 mb-1.5">Display name</span>
                    <input wire:model.live="name" type="text" class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
                    @error('name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="block text-sm font-semibold text-ink/70 mb-1.5">Handle</span>
                    <div class="flex items-center rounded-xl border border-ink/15 focus-within:border-moss overflow-hidden">
                        <span class="pl-3.5 text-ink/65 text-sm">&commat;</span>
                        <input wire:model.live="handle" type="text" class="flex-1 border-0 focus:ring-0 bg-transparent">
                    </div>
                    @error('handle')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="block text-sm font-semibold text-ink/70 mb-1.5">Tagline <span class="font-normal text-ink/65">— one line, shown big</span></span>
                    <input wire:model.live="tagline" type="text" placeholder="Sunrise treks &amp; island escapes" class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
                    @error('tagline')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </label>
                <label class="block">
                    <span class="block text-sm font-semibold text-ink/70 mb-1.5">Based in</span>
                    <input wire:model.live="location" type="text" placeholder="Baguio, Philippines" class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
                    @error('location')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </label>
            </div>
            <label class="block mt-5">
                <span class="block text-sm font-semibold text-ink/70 mb-1.5">Bio</span>
                <textarea wire:model.live="bio" rows="3" placeholder="Who you are and the kind of trips you run." class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss"></textarea>
                @error('bio')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
        </section>

        {{-- ══════════ PERSONALITY ══════════ --}}
        <section class="rounded-2xl border border-ink/10 bg-surface p-6">
            <h2 class="font-semibold text-lg">Your personality</h2>
            <p class="text-sm text-ink/65 mt-1 mb-5">When several hosts run the same hike, this is what joiners choose between.</p>

            <span class="block text-sm font-semibold text-ink/70 mb-2">How your trips feel <span class="font-normal text-ink/65">(pick up to {{ $maxVibes }})</span></span>
            <div class="flex flex-wrap gap-2 mb-2" role="group" aria-label="How your trips feel">
                @foreach($vibeCatalog as $key => $label)
                    @php($on = in_array($key, $vibes, true))
                    <label class="cursor-pointer">
                        <input type="checkbox" wire:model.live="vibes" value="{{ $key }}" dusk="vibe-{{ $key }}" class="peer sr-only" @disabled(! $on && count($vibes) >= $maxVibes)>
                        <span class="inline-flex items-center rounded-full border border-ink/15 px-3.5 py-2 text-sm font-semibold transition hover:border-ink/30 peer-checked:border-pine peer-checked:bg-pine peer-checked:text-cream peer-focus-visible:ring-2 peer-focus-visible:ring-moss peer-focus-visible:ring-offset-2 peer-disabled:cursor-not-allowed peer-disabled:opacity-40">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('vibes')<p class="text-terra-deep text-xs mb-2">{{ $message }}</p>@enderror
            @error('vibes.*')<p class="text-terra-deep text-xs mb-2">{{ $message }}</p>@enderror

            <span class="block text-sm font-semibold text-ink/70 mt-6 mb-2">Why join you <span class="font-normal text-ink/65">(up to three short lines)</span></span>
            <div class="space-y-2.5">
                @foreach(['Small groups, never more than 10', 'Our guides carry first aid and know every trail', 'Hot breakfast waiting at the summit'] as $i => $example)
                    <input wire:model.live.debounce.400ms="highlights.{{ $i }}" type="text" maxlength="80" placeholder="{{ $example }}" aria-label="Reason {{ $i + 1 }}" dusk="highlight-{{ $i }}"
                           class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
                    @error('highlights.'.$i)<p class="text-terra-deep text-xs">{{ $message }}</p>@enderror
                @endforeach
            </div>
        </section>

        {{-- ══════════ LOOK ══════════ --}}
        <section class="rounded-2xl border border-ink/10 bg-surface p-6">
            <h2 class="font-semibold text-lg">Look</h2>
            <p class="text-sm text-ink/65 mt-1 mb-5">Your accent tints your cover, tabs and highlights.</p>

            <span class="block text-sm font-semibold text-ink/70 mb-2">Accent</span>
            <div class="flex flex-wrap gap-2.5 mb-6">
                @foreach($palette as $key => $meta)
                    <button type="button" wire:click="$set('accent', '{{ $key }}')"
                            aria-label="Accent {{ $meta['label'] }}" aria-pressed="{{ $accent === $key ? 'true' : 'false' }}"
                            class="group flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold transition-all
                                   {{ $accent === $key ? 'border-ink/30 shadow-sm' : 'border-ink/12 hover:border-ink/25' }}">
                        <span class="w-4 h-4 rounded-full ring-1 ring-black/10" style="background:{{ $meta['hex'] }}"></span>
                        {{ $meta['label'] }}
                    </button>
                @endforeach
            </div>
            @error('accent')<p class="text-terra text-xs mb-3">{{ $message }}</p>@enderror

            <span class="block text-sm font-semibold text-ink/70 mb-2">Page backdrop</span>
            <div class="flex flex-wrap gap-2.5 mb-6">
                @foreach($backdrops as $key => $label)
                    <button type="button" wire:click="$set('backdrop', '{{ $key }}')" aria-pressed="{{ $backdrop === $key ? 'true' : 'false' }}" dusk="backdrop-{{ $key }}"
                            class="w-28 overflow-hidden rounded-xl border text-left transition {{ $backdrop === $key ? 'border-ink/40 ring-2 ring-ink/10' : 'border-ink/12 hover:border-ink/25' }}">
                        <span class="backdrop backdrop-{{ $key }} block h-14" style="--accent: {{ $palette[$accent]['hex'] ?? '#5E6B44' }}; background: {{ $palette[$accent]['soft'] ?? '#EDF0E6' }}" aria-hidden="true"></span>
                        <span class="block px-3 py-2 text-xs font-semibold">{{ $label }}</span>
                    </button>
                @endforeach
            </div>
            @error('backdrop')<p class="text-terra text-xs mb-3">{{ $message }}</p>@enderror

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <x-upload wire:model="avatar" label="Profile photo" :multiple="false" :max="1" />
                    @error('avatar')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <x-upload wire:model="cover" label="Cover photo" :multiple="false" :max="1"
                              hint="Wide crop — shown across the top of your page." />
                    @error('cover')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        {{-- ══════════ LINKS ══════════ --}}
        <section class="rounded-2xl border border-ink/10 bg-surface p-6">
            <h2 class="font-semibold text-lg mb-5">Links</h2>
            <div class="grid sm:grid-cols-3 gap-5">
                @foreach(['instagram' => 'Instagram', 'facebook' => 'Facebook', 'website' => 'Website'] as $k => $label)
                    <label class="block">
                        <span class="block text-sm font-semibold text-ink/70 mb-1.5">{{ $label }}</span>
                        <input wire:model="links.{{ $k }}" type="text" placeholder="{{ $k === 'website' ? 'example.com' : '@yourhandle' }}" class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
                    </label>
                @endforeach
            </div>
        </section>

        {{-- ══════════ GETTING PAID ══════════ --}}
        <section class="rounded-2xl border border-ink/10 bg-surface p-6">
            <h2 class="font-semibold text-lg">Getting paid</h2>
            <p class="text-sm text-ink/65 mt-1 mb-5">Joiners pay you directly — Tarana never holds your money.</p>

            <label class="block max-w-[10rem] mb-5">
                <span class="block text-sm font-semibold text-ink/70 mb-1.5">Currency</span>
                <input wire:model="currency" type="text" maxlength="3" class="w-full rounded-xl border-ink/15 uppercase focus:border-moss focus:ring-moss">
                @error('currency')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>

            <div class="space-y-4">
                @foreach($payment_methods as $i => $m)
                    <div class="rounded-2xl border border-ink/10 p-4" wire:key="pm-{{ $i }}">
                        <div class="flex flex-wrap gap-3 items-start">
                            <input wire:model="payment_methods.{{ $i }}.label" type="text" placeholder="GCash" aria-label="Payment method" class="w-40 rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
                            <input wire:model="payment_methods.{{ $i }}.detail" type="text" placeholder="0917 000 0000" aria-label="Account number" class="flex-1 min-w-[10rem] rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
                            {{-- The name on the account, so a joiner can check the e-wallet's
                                 confirmation screen matches before they send anything. --}}
                            <input wire:model="payment_methods.{{ $i }}.name" type="text" placeholder="Account name" aria-label="Account name" class="flex-1 min-w-[10rem] rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
                            <button type="button" wire:click="removePaymentMethod({{ $i }})" aria-label="Remove payment method" class="mt-2.5 text-ink/65 hover:text-terra">
                                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                            </button>
                        </div>
                        @error('payment_methods.'.$i.'.label')<p class="text-terra text-xs mt-1.5">{{ $message }}</p>@enderror
                        @error('payment_methods.'.$i.'.detail')<p class="text-terra text-xs mt-1.5">{{ $message }}</p>@enderror
                        @error('payment_methods.'.$i.'.name')<p class="text-terra text-xs mt-1.5">{{ $message }}</p>@enderror

                        {{-- e-wallet QR: joiners scan this instead of typing a number --}}
                        <div class="mt-3 flex items-start gap-4">
                            @if($m['qr'] ?? null)
                                <div class="shrink-0 text-center">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($m['qr']) }}"
                                         alt="{{ $m['label'] ?? '' }} QR code" class="w-24 h-24 rounded-xl object-cover border border-ink/10">
                                    <button type="button" wire:click="removeQr({{ $i }})" class="mt-1.5 text-xs font-semibold text-terra">Remove QR</button>
                                </div>
                            @endif
                            <div class="flex-1">
                                <x-upload wire:model="payment_qr.{{ $i }}" label="" :multiple="false" :max="1"
                                          hint="Optional — upload your e-wallet QR so joiners can just scan it." />
                                @error('payment_qr.'.$i)<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <button type="button" wire:click="addPaymentMethod" class="mt-4 text-sm font-semibold text-moss">＋ Add a method</button>
        </section>

        <div class="flex items-center gap-3 pb-4">
            <button type="submit" wire:loading.attr="disabled" class="rounded-full bg-pine text-cream px-7 py-3 font-semibold hover:bg-pine/90 transition-colors">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
            @if($user->handle)
                <a href="{{ route('hosts.show', $user) }}" class="text-sm font-semibold text-ink/65 hover:text-ink">View my page ↗</a>
            @endif
        </div>
    </form>
    </div>

    <aside class="mt-8 space-y-5 lg:sticky lg:top-6 lg:mt-0" aria-label="How your page looks to joiners">
        <section class="rounded-2xl border border-ink/10 bg-surface p-5" dusk="completeness">
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="font-semibold">Page completeness</h2>
                <span class="text-sm font-semibold tabular-nums">{{ $completeness['percent'] }}%</span>
            </div>
            <div class="mt-3 h-2 rounded-full bg-ink/10" role="progressbar" aria-valuenow="{{ $completeness['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Page completeness">
                <div class="h-2 rounded-full bg-pine transition-all duration-500" style="width: {{ $completeness['percent'] }}%"></div>
            </div>
            @if($completeness['todo'])
                <ul class="mt-4 space-y-2.5 text-sm">
                    @foreach(array_slice($completeness['todo'], 0, 4, true) as $item)
                        <li>
                            <span class="block font-semibold">{{ $item['label'] }}</span>
                            <span class="block text-ink/65">{{ $item['hint'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="mt-4 text-sm text-ink/70">Everything is filled in. Joiners get a full picture of who you are.</p>
            @endif
        </section>

        <section>
            <h2 class="mb-2 text-sm font-semibold text-ink/70">How you look in the directory</h2>
            <x-host-card :host="$previewHost" preview />
        </section>
    </aside>
    </div>
</div>
