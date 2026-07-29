<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class MobileNavTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_a_host_can_reach_every_console_page_from_a_phone(): void
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco', 'name' => 'Trail Collective']);
        HostProfile::factory()->for($host)->create();

        $this->browse(function (Browser $browser) use ($host) {
            $browser->loginAs($host)
                ->resize(390, 844)
                ->visit('/host')
                ->waitForText('Host dashboard')
                // the drawer is the only route to the rest of the console on a phone
                ->assertDontSee('Meetup points')
                ->click('@mobile-menu')
                ->waitForText('Meetup points')
                ->assertSee('Your page')
                ->assertSee('Settings')
                ->assertSee('Log out')
                ->click('@mobile-nav-close')
                ->pause(400)
                ->assertDontSee('Meetup points')
                // and it actually navigates
                ->click('@mobile-menu')
                ->waitForText('Meetup points')
                ->clickLink('Meetup points')
                ->waitForText('Save the spots you use often');
        });
    }

    public function test_trail_controls_keep_44px_targets_on_a_phone(): void
    {
        $host = User::factory()->create(['role' => 'host']);
        HostProfile::factory()->for($host)->create();
        $pulag = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike']);

        $this->browse(function (Browser $browser) use ($host, $pulag) {
            $browser->resize(375, 812)
                ->loginAs($host)
                ->visit('/host/trips/create')
                ->waitForText('Where are you going?')
                ->type('@destination-search', 'pulag')
                ->waitFor('@place-option-'.$pulag->id)
                ->click('@place-option-'.$pulag->id)
                ->waitForText('The trail')
                ->waitFor('@hike-difficulty-5-'.$pulag->id)
                ->pause(400);

            // every grade button must still be tappable at phone width
            $tooSmall = $browser->script(
                "return Array.from(document.querySelectorAll('[dusk^=hike-difficulty-]'))
                    .map(el => el.getBoundingClientRect())
                    .filter(r => r.width < 44 || r.height < 44).length;"
            )[0];

            $this->assertSame(0, $tooSmall, 'trail grade buttons fell below the 44px touch target');

            // and the page must not scroll sideways
            $offenders = $browser->script(
                "const w = document.documentElement.clientWidth;
                 // leaf-most nodes that are themselves too wide: the real cause, not their parents
                 return Array.from(document.querySelectorAll('*'))
                    .filter(el => el.getBoundingClientRect().width > w &&
                                  !Array.from(el.children).some(c => c.getBoundingClientRect().width > w))
                    .slice(0, 8)
                    .map(el => el.tagName + '.' + (el.className.baseVal ?? el.className ?? '').toString().slice(0, 80)
                               + ' w=' + Math.round(el.getBoundingClientRect().width));"
            )[0];
            $overflow = $browser->script(
                'return document.documentElement.scrollWidth - document.documentElement.clientWidth;'
            )[0];
            $this->assertLessThanOrEqual(1, $overflow,
                "wizard overflows horizontally by {$overflow}px on a phone: ".json_encode($offenders, JSON_PRETTY_PRINT));
        });
    }

    public function test_the_wizard_shows_progress_on_a_phone(): void
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
        HostProfile::factory()->for($host)->create();

        $this->browse(function (Browser $browser) use ($host) {
            $browser->loginAs($host)
                ->resize(390, 844)
                ->visit('/host/trips/create')
                ->waitForText('Where are you going?')
                // the desktop rail is hidden here, so the compact stepper must carry it
                ->assertSee('Step 1 of 5')
                ->assertSee("Where you're going");
        });
    }
}
