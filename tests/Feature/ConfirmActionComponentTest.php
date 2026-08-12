<?php

use Illuminate\Support\Facades\Blade;

/*
 * The confirm dialog runs one Livewire call. If that expression is not a *call*, the button
 * renders, opens, closes — and does nothing at all, with no error anywhere. That is exactly how
 * "Cancel reservation" shipped broken: it was written `action="cancel"` while every other call
 * site wrote `method(args)`.
 */
it('calls a bare method name rather than referencing it', function () {
    $html = Blade::render(
        '<x-confirm-action action="cancel" title="Sure?">Go</x-confirm-action>'
    );

    expect($html)->toContain('$wire.cancel()')
        ->and($html)->not->toContain('$wire.cancel"');
});

it('leaves an action that already has arguments alone', function () {
    $html = Blade::render(
        '<x-confirm-action action="confirm(12)" title="Sure?">Go</x-confirm-action>'
    );

    expect($html)->toContain('$wire.confirm(12)')
        ->and($html)->not->toContain('confirm(12)()');
});
