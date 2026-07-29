<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TripWizardFlowTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_a_host_publishes_a_multi_destination_trip(): void
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

        $pulag = Place::factory()->create(['name' => 'Mount Pulag', 'country' => 'Philippines']);
        Place::factory()->create(['name' => 'Sagada', 'country' => 'Philippines']);

        $this->browse(function (Browser $browser) use ($host, $pulag) {
            $browser->loginAs($host)
                ->visit('/host/trips/create')
                ->waitForText('New trip')
                // options are <button>s, so click the dusk hook rather than clickLink()
                ->type('@destination-search', 'pulag')
                ->waitFor('@place-option-'.$pulag->id)
                ->click('@place-option-'.$pulag->id)
                ->waitForText('Mount Pulag')
                ->click('@wizard-next')
                // step 2 is the trip itself: title, photos, price
                ->waitForText('The trip')
                ->type('@trip-title', 'Dusk Traverse')
                ->type('@trip-price', '1500')
                ->click('@wizard-next')
                ->waitForText('Everything a joiner gets for the price.')
                ->click('@wizard-next')
                ->waitForText('Logistics')
                ->type('@dep-0-start', now()->addWeek()->format('Y-m-d'))
                ->click('@wizard-next')
                ->waitForText('Review')
                ->click('@wizard-publish')
                ->waitForText('Dusk Traverse');
        });

        $this->assertDatabaseHas('trips', ['title' => 'Dusk Traverse', 'place_id' => $pulag->id]);
    }

    public function test_a_host_grades_the_trail_and_it_reaches_the_public_page(): void
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $pulag = Place::factory()->create(['name' => 'Mount Pulag', 'country' => 'Philippines', 'category' => 'hike']);

        $this->browse(function (Browser $browser) use ($host, $pulag) {
            $browser->loginAs($host)
                ->visit('/host/trips/create')
                ->waitForText('New trip')
                ->type('@destination-search', 'pulag')
                ->waitFor('@place-option-'.$pulag->id)
                ->click('@place-option-'.$pulag->id)
                ->waitForText('Mount Pulag')
                // the trail sits inside the destination card, no disclosure to open
                ->waitForText('The trail')
                ->waitFor('@hike-masl-'.$pulag->id)
                ->type('@hike-masl-'.$pulag->id, '2922')
                ->type('@hike-distance_km-'.$pulag->id, '14.5')
                ->click('@hike-difficulty-5-'.$pulag->id)
                ->waitForText('Major climb')
                ->click('@hike-type-traverse-'.$pulag->id)
                // the feature tiles are always on screen now
                ->click('@hike-feature-monkey_trail-'.$pulag->id)
                ->click('@wizard-next')
                ->waitForText('The trip')
                ->type('@trip-title', 'Graded Traverse')
                ->type('@trip-price', '1500')
                ->click('@wizard-next')
                ->waitForText('Everything a joiner gets for the price.')
                ->click('@wizard-next')
                ->waitForText('Logistics')
                ->type('@dep-0-start', now()->addWeek()->format('Y-m-d'))
                ->click('@wizard-next')
                ->waitForText('Review')
                ->click('@wizard-publish')
                ->waitForText('Graded Traverse')
                ->assertSee('The trail')
                ->assertSee('2,922 m')
                ->assertSee('Major climb')
                ->assertSee('Monkey trail');
        });

        $this->assertDatabaseHas('trip_hike_details', [
            'masl' => 2922, 'difficulty' => 5, 'trail_type' => 'traverse',
        ]);
    }
}
