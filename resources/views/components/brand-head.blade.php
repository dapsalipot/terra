{{--
    What a page's <head> needs to be recognisably Tarana: the wordmark's font, preloaded so the logo does
    not swap in late, and the icon set. The SVG is what current browsers use, the ICO covers the rest, and
    the PNG is the iOS home-screen icon (iOS applies its own rounded mask, so that one is a full square).
--}}
<link rel="preload" href="/fonts/fredoka.woff2" as="font" type="font/woff2" crossorigin>
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" href="/favicon.svg" type="image/svg+xml" sizes="any">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
