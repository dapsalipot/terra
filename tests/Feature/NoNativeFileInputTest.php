<?php

use Symfony\Component\Finder\Finder;

/**
 * The browser's own file control — grey "Choose File" button, "No file chosen" beside it — is
 * the one piece of chrome on any page that nobody designed, and it renders differently on every
 * OS. Every upload goes through <x-upload>, which keeps a real input in the DOM but hides it
 * visually, so Livewire's plumbing and the no-JS path are untouched.
 *
 * A lint, not a behaviour test: it fails the moment someone drops a bare <input type="file">
 * into a view, which is when it is cheapest to fix.
 */
it('never renders a visible native file input', function () {
    $offenders = [];

    $files = Finder::create()->files()->in(resource_path('views'))->name('*.blade.php');

    foreach ($files as $file) {
        $relative = str_replace(resource_path('views').'/', '', $file->getRealPath());

        // The shared uploader is where the one sanctioned input lives, and its own comment
        // quotes the tag it is replacing — both of which a naive scan reads as violations.
        if ($relative === 'components/upload.blade.php') {
            continue;
        }

        $html = $file->getContents();
        $offset = 0;

        while (($at = strpos($html, 'type="file"', $offset)) !== false) {
            $offset = $at + 1;

            /*
             * Scan the whole tag, not the line. Attributes wrap, so `class="sr-only"` regularly
             * sits a line below the `type="file"` that it belongs to — a line-based check calls
             * those false positives and misses nothing useful in exchange.
             */
            $tagStart = strrpos(substr($html, 0, $at), '<input');
            $tagEnd = strpos($html, '>', $at);

            if ($tagStart === false || $tagEnd === false) {
                continue;
            }

            $tag = substr($html, $tagStart, $tagEnd - $tagStart);

            // Hidden either way is fine: sr-only keeps it reachable by keyboard and screen
            // reader, `hidden` is for inputs sitting behind their own styled trigger.
            if (str_contains($tag, 'sr-only') || str_contains($tag, 'hidden')) {
                continue;
            }

            $offenders[] = $relative.' line '.(substr_count(substr($html, 0, $at), "\n") + 1);
        }
    }

    expect($offenders)->toBe([], 'Use <x-upload> instead of a bare file input: '.implode(', ', $offenders));
});

it('catches a bare file input that someone adds later', function () {
    // Proves the lint above can actually fail — a scan that matched nothing would pass just
    // as quietly on a page full of native controls.
    $tag = '<input type="file" wire:model="proof" class="block w-full">';

    expect(str_contains($tag, 'sr-only') || str_contains($tag, 'hidden'))->toBeFalse();
});
