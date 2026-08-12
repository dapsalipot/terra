{{-- A walk-in: somebody who paid the host in cash and never touched the app. Confirmed on
     creation, because the host is the one saying they are coming. --}}
<div class="rounded-2xl border border-ink/10 bg-surface p-4 mb-4" dusk="add-guest-form">
    <p class="font-semibold text-sm mb-3">Add someone who booked off-app</p>
    <div class="sm:flex sm:items-end sm:gap-3">
        <label class="block sm:flex-1 sm:min-w-0">
            <span class="field-label">Name</span>
            <input wire:model="guest_name" class="field" placeholder="Walk-in name" dusk="guest-name">
            @error('guest_name')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
        </label>
        <label class="block mt-3 sm:mt-0 sm:w-28 sm:shrink-0">
            <span class="field-label">Seats</span>
            <input type="number" wire:model="guest_party" class="field" dusk="guest-party" min="1">
            @error('guest_party')<p class="text-terra text-xs mt-1">{{ $message }}</p>@enderror
        </label>
        <button wire:click="addOffline" dusk="add-offline"
                class="mt-3 sm:mt-0 w-full sm:w-auto rounded-full bg-moss text-cream px-6 min-h-[44px] font-semibold hover:bg-moss/90 transition-colors">Add</button>
    </div>
</div>
