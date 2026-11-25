<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class BrandTest extends DuskTestCase
{
    public function test_the_logo_font_loads_and_the_phone_nav_still_fits(): void
    {
        $this->browse(function (Browser $browser) {
            // A typo in the font path would fail nothing else: the word would just quietly fall back to Geist.
            $browser->visit('/')
                ->waitUsing(5, 100, fn () => $browser->script("return [...document.fonts].some(f => f.family.includes('Fredoka') && f.status === 'loaded')")[0]);

            // Fredoka runs wider than Geist did, and the guest nav on a phone is already full.
            $browser->resize(375, 812)->visit('/');
            $overflow = $browser->script('return document.documentElement.scrollWidth - innerWidth')[0];
            $this->assertLessThanOrEqual(0, $overflow, 'The guest nav overflows a 375px phone.');

            $browser->resize(1920, 1080);
        });
    }
}
