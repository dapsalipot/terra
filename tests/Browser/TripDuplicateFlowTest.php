<?php
namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TripDuplicateFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_copy_then_walk_every_step(): void
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco', 'name' => 'Trail Collective']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $pulag = Place::factory()->create(['name' => 'Mount Pulag', 'country' => 'Philippines', 'category' => 'hike', 'elevation_m' => 2926]);

        $trip = Trip::create([
            'host_id' => $host->id, 'place_id' => $pulag->id, 'title' => 'Pulag Sunrise',
            'description' => 'Cold dawn.', 'price' => 2500, 'status' => 'published',
        ]);
        $trip->syncDestinations([['place_id' => $pulag->id, 'days' => ['Trek to Camp 2', 'Summit push']]]);
        $trip->destinations->first()->hikeDetail()->create(['masl' => 2922, 'difficulty' => 5, 'features' => ['mossy_forest']]);
        $trip->meetups()->create(['kind' => 'pickup', 'label' => 'Cubao terminal', 'time' => '22:00', 'lat' => 14.6220, 'lng' => 121.0530, 'position' => 0]);
        $trip->meetups()->create(['kind' => 'dropoff', 'label' => 'Cubao return', 'time' => '18:30', 'position' => 0]);
        $trip->inclusionItems()->create(['label' => 'Aircon van', 'position' => 0]);
        TripDeparture::factory()->for($trip)->create(['starts_at' => now()->addMonth()]);

        $this->browse(function (Browser $browser) use ($host, $trip) {
            $browser->loginAs($host)
                ->visit('/host/trips/create/from/'.$trip->slug)
                ->waitForText('Where are you going?')
                ->assertSee('Mount Pulag')
                ->click('@wizard-next')->waitForText('The trip')
                ->click('@wizard-next')->waitForText('Everything a joiner gets')
                ->click('@wizard-next')->waitForText('Logistics')
                ->pause(500);

            // The cards auto-open when they hold content, so the copied spots are in the
            // inputs rather than in the collapsed summary line.
            $spots = $browser->script(
                'return Array.from(document.querySelectorAll("input")).map(i => i.value).filter(Boolean);'
            )[0];
            $this->assertContains('Cubao terminal', $spots, 'copied pickup point missing');
            $this->assertContains('Cubao return', $spots, 'copied drop-off missing');

            $browser
                // a copy deliberately carries no dates, so supply one
                ->type('@dep-0-start', now()->addWeek()->format('Y-m-d'))
                ->click('@wizard-next')->waitForText('Review')
                // the review screen renders these as text, so they are assertable
                ->assertSee('Pulag Sunrise (copy)')
                ->assertSee('Trek to Camp 2')
                ->assertSee('Cubao terminal')
                ->assertSee('Cubao return')
                ->assertSee('22:00')
                ->assertSee('18:30')
                ->assertSee('pinned');
        });
    }
}
