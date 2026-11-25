{{-- `label` is nullable: inside a card already titled "Price", repeating it is noise. --}}
@props(['currency' => 'PHP', 'suffix' => 'per person', 'label' => null])

<div>
    @if($label)
        <span class="block text-sm font-semibold text-ink/70 mb-1.5">{{ $label }}</span>
    @endif
    <div class="flex items-stretch rounded-xl border border-ink/15 overflow-hidden focus-within:border-moss focus-within:ring-1 focus-within:ring-moss">
        <span class="grid place-items-center px-4 bg-ink/[.04] text-sm font-bold text-ink/70 border-r border-ink/10">{{ $currency }}</span>
        <input {{ $attributes->merge(['class' => 'flex-1 min-w-0 border-0 focus:ring-0 font-serif text-xl px-4 py-3']) }}
               type="text" inputmode="decimal" aria-label="Price {{ $suffix }} in {{ $currency }}" placeholder="2,000">
        <span class="grid place-items-center px-4 text-sm text-ink/65 border-l border-ink/10 whitespace-nowrap">{{ $suffix }}</span>
    </div>
</div>
