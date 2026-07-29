<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\User;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\DuskTestCase;

class CoverUploadTest extends DuskTestCase
{
    use DatabaseTruncation;

    public function test_the_wizard_accepts_a_cover_upload_and_previews_it(): void
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline']);
        HostProfile::factory()->for($host)->create();
        $place = Place::factory()->create(['name' => 'Mount Rinjani']);

        $this->browse(function (Browser $browser) use ($host, $place) {
            $browser->loginAs($host)
                ->visit('/host/trips/create')
                ->waitForText('Where are you going?')
                ->type('@destination-search', 'rinjani')
                ->waitFor('@place-option-'.$place->id)
                ->click('@place-option-'.$place->id)
                ->waitForText('Mount Rinjani')
                ->click('@wizard-next')          // photos live on step 2 with the rest of the trip
                ->waitFor('@cover')
                ->attach('@cover', base_path('tests/fixtures/cover.png'))
                ->waitFor('img')                 // temporaryUrl preview appears once uploaded
                ->assertPresent('img');
        });
    }
}
