@props(['meta' => null])

{{--
    How a page looks when its link is shared, and which address it answers to: the tags Facebook, Messenger,
    X and most chat apps read, plus the canonical link and, for a page that is not public, a noindex.

    `meta` is what App\Support\PageMeta returns. A page that says nothing of its own gets the default card.
    Every value goes through {{ }}, so a trip called Pulag "Sea of Clouds" & Beyond cannot break out of its tag.
--}}
@php($m = $meta ?? \App\Support\PageMeta::default())
<meta name="description" content="{{ $m['description'] }}">
<link rel="canonical" href="{{ $m['url'] }}">
@if($m['noindex'])
    <meta name="robots" content="noindex">
@endif
<meta property="og:site_name" content="Tarana">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $m['title'] }}">
<meta property="og:description" content="{{ $m['description'] }}">
<meta property="og:url" content="{{ $m['url'] }}">
<meta property="og:image" content="{{ $m['image'] }}">
<meta name="twitter:card" content="summary_large_image">
