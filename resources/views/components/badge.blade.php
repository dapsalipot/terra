@props([
    'tone' => 'ink',   // moss = settled/good · amber = waiting · terra = needs attention · ink = neutral
    'dot' => false,    // a leading dot, for states that should catch the eye
])

{{--
    One shape for every status in the app.

    Status was being drawn a different way each time it appeared — a tick and bold text here, a
    coloured dot and text there, a bare number somewhere else — so nothing read as the same kind
    of fact. A pill is the convention for exactly this, and having one component means the next
    status added cannot invent a fourth style.
--}}
@php
    $tones = [
        'moss' => 'bg-moss/12 text-pine',
        'amber' => 'bg-amber/15 text-amber',
        'terra' => 'bg-terra/12 text-terra',
        'ink' => 'bg-ink/[.06] text-ink/65',
    ];
    $dots = ['moss' => 'bg-moss', 'amber' => 'bg-amber', 'terra' => 'bg-terra', 'ink' => 'bg-ink/40'];
@endphp

<span {{ $attributes->class([
    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold whitespace-nowrap',
    $tones[$tone] ?? $tones['ink'],
]) }}>
    @if($dot)
        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $dots[$tone] ?? $dots['ink'] }}" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
