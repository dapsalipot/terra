<div class="max-w-3xl">
    <div class="flex items-end justify-between mb-6">
        <div>
            <h1 class="font-serif font-semibold text-3xl">Meetup points</h1>
            <p class="text-ink/65 mt-1 text-sm">Save the spots you use often, then pick them when you post a trip.</p>
        </div>
        @if($saved)
            <span class="rounded-full bg-moss/12 text-moss px-4 py-2 text-sm font-semibold">{{ $saved }}</span>
        @endif
    </div>

    <form wire:submit="save" class="rounded-2xl border border-ink/10 bg-surface p-6 mb-8">
        <h2 class="font-serif font-semibold text-lg mb-4">{{ $editingId ? 'Edit spot' : 'Add a spot' }}</h2>

        <label class="block mb-4">
            <span class="block text-sm font-semibold text-ink/70 mb-1.5">Name</span>
            <input wire:model="label" type="text" placeholder="Cubao terminal · 10:00 PM"
                   class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
            @error('label')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
        </label>

        <label class="block mb-4">
            <span class="block text-sm font-semibold text-ink/70 mb-1.5">Address</span>
            <input wire:model="address" type="text" placeholder="EDSA cor. Aurora Blvd, Quezon City"
                   class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
            @error('address')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
        </label>

        <label class="block mb-4">
            <span class="block text-sm font-semibold text-ink/70 mb-1.5">Note</span>
            <input wire:model="note" type="text" placeholder="Look for the blue van by Gate 2"
                   class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
            @error('note')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
        </label>

        <div class="grid grid-cols-2 gap-4">
            <label class="block">
                <span class="block text-sm font-semibold text-ink/70 mb-1.5">Latitude</span>
                <input wire:model="lat" type="text" inputmode="decimal" placeholder="14.6220"
                       class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
                @error('lat')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
            <label class="block">
                <span class="block text-sm font-semibold text-ink/70 mb-1.5">Longitude</span>
                <input wire:model="lng" type="text" inputmode="decimal" placeholder="121.0530"
                       class="w-full rounded-xl border-ink/15 focus:border-moss focus:ring-moss">
                @error('lng')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
            </label>
        </div>
        <p class="text-[11px] text-ink/65 mt-2">In Google Maps, right-click the spot → click the coordinates to copy, then paste them here.</p>

        <div class="mt-4">
            <x-upload wire:model="image" label="Photo of the spot" :multiple="false" :max="1" />
            @error('image')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center gap-3 mt-6">
            <button type="submit" class="rounded-full bg-pine text-cream px-6 py-3 font-semibold text-sm hover:bg-pine/90 transition-colors">
                {{ $editingId ? 'Save changes' : 'Add spot' }}
            </button>
            @if($editingId)
                <button type="button" wire:click="cancel" class="text-sm font-semibold text-ink/65 hover:text-ink">Cancel</button>
            @endif
        </div>
    </form>

    <div class="space-y-3" dusk="preset-list">
        @forelse($presets as $preset)
            <div class="flex items-start gap-4 rounded-2xl border border-ink/10 bg-surface p-4" wire:key="preset-{{ $preset->id }}">
                @if($preset->image_url)
                    <img src="{{ $preset->image_url }}" alt="" class="w-20 h-20 rounded-xl object-cover shrink-0">
                @endif
                <div class="min-w-0 flex-1">
                    <div class="font-semibold">{{ $preset->label }}</div>
                    @if($preset->address)<p class="text-sm text-ink/65 mt-0.5">{{ $preset->address }}</p>@endif
                    @if($preset->note)<p class="text-sm text-ink/70 mt-1 italic">{{ $preset->note }}</p>@endif
                    @if($preset->lat && $preset->lng)
                        <p class="text-xs text-ink/65 mt-1.5">Pinned · {{ $preset->lat }}, {{ $preset->lng }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button wire:click="edit({{ $preset->id }})" class="text-sm font-semibold text-ink/65 hover:text-ink">Edit</button>
                    <x-confirm-action
                        action="delete({{ $preset->id }})"
                        title="Delete this meetup point?"
                        body="{{ $preset->label }} is removed from your saved spots. Trips already using it keep their own copy, so nothing published changes."
                        confirm-label="Yes, delete it"
                        cancel-label="Keep it"
                        tone="terra" icon="trash"
                        dusk="delete-preset-{{ $preset->id }}"
                        class="text-sm font-semibold text-terra">Delete</x-confirm-action>
                </div>
            </div>
        @empty
            <div class="text-center py-12 rounded-2xl border border-dashed border-ink/15">
                <p class="font-serif text-lg text-ink/70">No saved spots yet.</p>
                <p class="text-ink/65 text-sm mt-1">Add the terminal or trailhead you use most.</p>
            </div>
        @endforelse
    </div>
</div>
