@props(['lat' => null, 'lng' => null, 'label' => 'this spot'])

@php
    // Only embed a map once both coordinates are present and plausible — a half-typed
    // latitude would otherwise drop the host somewhere in the ocean while they type.
    $ok = is_numeric($lat) && is_numeric($lng)
        && abs((float) $lat) <= 90 && abs((float) $lng) <= 180
        && ((float) $lat !== 0.0 || (float) $lng !== 0.0);
@endphp

@if($ok)
    <div class="mt-3 rounded-xl overflow-hidden border border-ink/12">
        <iframe src="https://maps.google.com/maps?q={{ $lat }},{{ $lng }}&z=15&output=embed"
                title="Map showing {{ $label }}"
                class="w-full h-56 block" style="border:0" loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"></iframe>
        <div class="flex items-center justify-between gap-3 px-3.5 py-2.5 bg-cream/60 text-sm">
            <span class="text-ink/70 truncate">{{ $lat }}, {{ $lng }}</span>
            <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}"
               target="_blank" rel="noopener noreferrer"
               class="shrink-0 font-semibold text-moss hover:underline">Open in Maps ↗</a>
        </div>
    </div>
@endif
