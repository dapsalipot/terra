<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
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
                // the tab bar carries the pages a host lives in; the rest sit behind More
                ->assertVisible('@tab-dashboard')
                ->assertVisible('@tab-new')
                ->assertDontSee('Meetup points')
                ->click('@tab-more')
                ->waitFor('@more-sheet')
                ->waitForText('Meetup points')
                ->assertSee('Settings')
                ->assertSee('Log out')
                ->keys('', '{escape}')
                ->waitUntilMissing('@more-sheet')
                ->assertDontSee('Meetup points')
                // and it actually navigates
                ->click('@tab-more')
                ->waitForText('Meetup points')
                ->clickLink('Meetup points')
                ->waitForText('Save the spots you use often');
        });
    }

    public function test_a_joiner_moves_between_tabs_and_logs_out_from_their_account(): void
    {
        $joiner = User::factory()->create(['role' => 'joiner']);

        $this->browse(function (Browser $browser) use ($joiner) {
            $browser->loginAs($joiner)
                ->resize(390, 844)
                ->visit('/')
                ->waitFor('@tab-explore')
                ->click('@tab-explore')
                ->waitForLocation('/trips')
                ->waitFor('@tab-explore')
                ->assertAttribute('@tab-explore', 'aria-current', 'page')
                ->click('@tab-account')
                ->waitForLocation('/profile')
                // the top bar no longer carries Log out on a phone, so the account page has to
                ->waitFor('@account-logout')
                ->click('@account-logout')
                ->waitForLocation('/')
                ->waitFor('@tab-login');
        });
    }

    public function test_a_guest_is_invited_to_host_and_lands_on_the_host_card(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(390, 844)
                ->visit('/trips')
                ->waitFor('@nav-host')
                ->click('@nav-host')
                ->waitForLocation('/register')
                ->waitFor('input[value=host]')
                ->assertScript("return document.querySelector('input[value=host]').checked", true);
        });
    }

    public function test_the_tab_bar_is_phone_only_and_steps_aside_on_the_booking_page(): void
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
        HostProfile::factory()->for($host)->create();
        $trip = Trip::factory()->published()->create(['host_id' => $host->id]);

        $this->browse(function (Browser $browser) use ($trip) {
            $browser->resize(1440, 900)
                ->visit('/')
                ->waitFor('@nav-host')
                ->assertMissing('@tab-home')
                ->resize(390, 844)
                ->visit('/')
                ->waitFor('@tab-home')
                ->assertVisible('@tab-home')
                // the trip page has its own reserve bar, and two bars at the bottom is one too many
                ->visit('/t/'.$trip->slug)
                ->pause(500)
                ->assertMissing('@tab-home');

            $browser->resize(1920, 1080);
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
