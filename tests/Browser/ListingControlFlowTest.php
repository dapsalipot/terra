<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\Visibility;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ListingControlFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** A host with one published, public trip running in three weeks. @return array{User, Trip} */
    private function offering(): array
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco', 'name' => 'Trail Collective']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $trip = Trip::factory()->published()->create([
            'host_id' => $host->id,
            'title' => 'Sagada Echo Valley Hike',
            'place_id' => Place::factory()->create(['name' => 'Sagada', 'country' => 'Philippines'])->id,
        ]);
        TripDeparture::factory()->for($trip)->create(['starts_at' => now()->addWeeks(3)]);

        return [$host, $trip];
    }

    public function test_a_host_can_take_an_offering_off_the_listings(): void
    {
        [$host, $trip] = $this->offering();
        $menu = '@visibility-solo-'.$trip->id;
        $apply = '@visibility-apply-solo-'.$trip->id;
        $chip = '@chip-'.$trip->id;

        $this->browse(function (Browser $browser) use ($host, $trip, $menu, $apply, $chip) {
            $browser->loginAs($host)
                // listed to everyone to begin with, which is what gives its absence below any meaning
                ->visit('/trips')
                ->assertSee($trip->title)
                ->visit('/host/trips')
                ->waitFor($menu)
                // Nothing to apply until something is picked, and picking is not applying: `select` fires the same
                // `change` an arrow key does on Windows, and it must not touch the listing.
                ->assertDisabled($apply)
                ->select($menu, 'link')
                ->assertEnabled($apply)
                ->pause(600)
                ->assertMissing($chip)
                ->click($apply)
                // "Link only" is also one of the menu's own options, so waiting for those words proves nothing:
                // the chip is drawn from the saved trip, so it is only there once the change has landed
                ->waitFor($chip)
                ->assertSeeIn($chip, 'Link only')
                // the menu is back on its prompt with nothing left to apply, rather than claiming to be the state,
                // and it has the focus, because the button that was just pressed has switched itself off
                ->assertSelected($menu, '')
                ->assertDisabled($apply)
                ->assertFocused($menu);

            // said aloud as well: the region is visually hidden, so read its text rather than look for it
            $spoken = $browser->script('return document.querySelector(\'[dusk="visibility-status"]\').textContent')[0];
            $this->assertSame('Who can see Sagada Echo Valley Hike: Link only.', trim($spoken));

            $browser->logout()
                ->visit('/trips')
                ->assertDontSee($trip->title)
                // off the listings is not off the internet: the link still opens it
                ->visit('/t/'.$trip->slug)
                ->assertSee($trip->title);
        });
    }

    /**
     * The closed select is the only place a host reads which window they are about to apply, so every window's
     * label has to fit in it on a small phone, and Apply has to sit under it rather than squeeze it.
     */
    public function test_a_picked_window_can_be_read_before_it_is_applied_on_a_small_phone(): void
    {
        [$host, $trip] = $this->offering();
        $menu = '@visibility-solo-'.$trip->id;

        $this->browse(function (Browser $browser) use ($host, $trip, $menu) {
            $browser->loginAs($host)
                ->resize(390, 844)
                ->visit('/host/trips')
                ->waitFor($menu)
                // text is measured in the font it is drawn in, or the widths below are for a fallback face
                ->waitUsing(5, 100, fn () => $browser->script("return [...document.fonts].some(f => f.family.includes('Geist') && f.status === 'loaded')")[0]);

            $this->assertLessThan(640, $browser->script('return innerWidth')[0], 'The phone layout (below sm) is what this measures.');

            foreach ([320, 360, 375] as $width) {
                // Narrow the console's own column to what a phone of this width leaves it, so the test does not
                // depend on how narrow the browser window will go.
                $browser->script("document.getElementById('host-main').style.cssText = 'width: {$width}px; max-width: {$width}px'");

                foreach (Visibility::WINDOWS as $window => [, $label]) {
                    $browser->select($menu, Visibility::FOLLOWERS.':'.$window);
                    [$needed, $room, $applyBeneath, $applyHeight] = $this->pickedLabel($browser, $trip->id);

                    $this->assertLessThanOrEqual($room, $needed, "At {$width}px the closed select has {$room}px of text room for \"Followers first for {$label}\", which needs {$needed}px.");
                    $this->assertTrue($applyBeneath, "At {$width}px Apply should wrap under the select.");
                    $this->assertGreaterThanOrEqual(44, $applyHeight, "At {$width}px Apply should still be a 44px target.");
                }
            }

            $browser->resize(1920, 1080);
        });
    }

    /** What the picked option needs and what the closed select has for it, in px, plus where and how tall Apply is: [needed, room, Apply under the select, Apply height]. */
    private function pickedLabel(Browser $browser, int $tripId): array
    {
        return $browser->script(<<<JS
            const select = document.querySelector('[dusk="visibility-solo-{$tripId}"]');
            const apply = document.querySelector('[dusk="visibility-apply-solo-{$tripId}"]').getBoundingClientRect();
            const style = getComputedStyle(select);

            const probe = document.createElement('span');
            probe.style.cssText = 'position: absolute; visibility: hidden; white-space: nowrap; font: ' + style.font + '; letter-spacing: ' + style.letterSpacing;
            probe.textContent = select.options[select.selectedIndex].text;
            document.body.appendChild(probe);
            const needed = probe.getBoundingClientRect().width;
            probe.remove();

            const room = select.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);

            return [needed, room, apply.top >= select.getBoundingClientRect().bottom - 1, apply.height];
            JS)[0];
    }
}
