<div class="max-w-4xl" x-data>
    <div class="flex items-end justify-between mb-6">
        <div>
            <h1 class="font-serif font-semibold text-3xl">Your page</h1>
            <p class="text-ink/65 mt-1 text-sm">Everything here is public at
                {{-- &commat; not a literal @, which Blade would read as the start of a directive --}}
                <a href="{{ $user->handle ? route('hosts.show', $user) : '#' }}" class="font-semibold underline decoration-ink/25">terra/&commat;{{ $handle ?: 'your-handle' }}</a>.
            </p>
        </div>
        @if($saved)
            <span class="rounded-full bg-moss/12 text-moss px-4 py-2 text-sm font-semibold">{{ $saved }}</span>
        @endif
    </div>

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
            <span class="relative -mt-10 grid place-items-center w-20 h-20 rounded-2xl ring-4 ring-surface overflow-hidden bg-pine text-cream font-serif font-semibold text-2xl shrink-0">
                @if($avatar)
                    <img src="{{ $avatar->temporaryUrl() }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                @elseif($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                @else
                    {{ strtoupper(mb_substr($name ?: 'H', 0, 1)) }}
                @endif
            </span>

            <div class="mt-3 min-w-0">
                <div class="font-serif font-semibold text-xl truncate">{{ $name ?: 'Your name' }}</div>
                <div class="text-sm text-ink/65 truncate">
                    &commat;{{ $handle ?: 'handle' }}@if($location) · {{ $location }}@endif
                </div>
            </div>
            @if($tagline)
                <p class="mt-3 font-serif italic text-lg" style="color:{{ \App\Support\Accents::hex($accent) }}">{{ $tagline }}</p>
            @endif
            @if($bio)<p class="mt-2 text-sm text-ink/70 leading-relaxed">{{ $bio }}</p>@endif
        </div>
    </div>

    <form wire:submit="save" class="space-y-8">
        {{-- ══════════ IDENTITY ══════════ --}}
        <section class="rounded-2xl border border-ink/10 bg-surface p-6">
            <h2 class="font-serif font-semibold text-lg mb-5">Identity</h2>
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

        {{-- ══════════ LOOK ══════════ --}}
        <section class="rounded-2xl border border-ink/10 bg-surface p-6">
            <h2 class="font-serif font-semibold text-lg">Look</h2>
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
            <h2 class="font-serif font-semibold text-lg mb-5">Links</h2>
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
            <h2 class="font-serif font-semibold text-lg">Getting paid</h2>
            <p class="text-sm text-ink/65 mt-1 mb-5">Joiners pay you directly — Terra never holds your money.</p>

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
