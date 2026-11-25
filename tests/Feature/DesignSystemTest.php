<?php

use Illuminate\Support\Facades\Blade;

it('renders the site layout with the Tarana wordmark and no emoji in chrome', function () {
    $html = Blade::render('<x-layouts.site><p>hello</p></x-layouts.site>');

    expect($html)
        ->toContain('Tarana')                          // wordmark present
        ->toContain('hello')                          // slot renders
        ->not->toMatch('/[\x{1F300}-\x{1FAFF}]/u');   // no emoji in chrome
});

it('exposes the Trail Earth palette on drawn icon components', function () {
    $html = Blade::render('<x-icon.mountain class="w-6 h-6 text-pine" />');

    expect($html)
        ->toContain('stroke-width="1.7"')             // drawn icon spec
        ->toContain('viewBox="0 0 24 24"')
        ->toContain('text-pine');
});
