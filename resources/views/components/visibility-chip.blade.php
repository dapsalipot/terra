@props(['trip'])

{{--
    How a trip is listed ("Link only", "Followers first until 5 Dec"), said to the host who chose it and to nobody else.

    A joiner never learns how a host listed a trip, and a trip everyone can find has nothing to add, so most renders
    are empty. Keep it off photographs: the tinted pill is only legible on a flat background, and the contrast audit
    skips text over a picture, so a chip there could never be checked.
--}}
@php($label = auth()->id() === $trip->host_id ? \App\Support\Visibility::chip($trip) : null)

@if($label)
    <x-badge :tone="$trip->effectiveVisibility() === \App\Support\Visibility::LINK ? 'ink' : 'amber'" {{ $attributes }}>{{ $label }}</x-badge>
@endif
