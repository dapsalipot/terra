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

class BookingSheetTest extends DuskTestCase
{
    use DatabaseTruncation;

    private function publishedTrip(): Trip
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
        $place = Place::factory()->create(['name' => 'Mount Pulag']);
        $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'place_id' => $place->id, 'title' => 'Sheet Trip', 'price' => 1500]);
        TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

        return $trip;
    }

    public function test_the_booking_sheet_collapses_and_expands_on_a_phone(): void
    {
        $trip = $this->publishedTrip();

        $this->browse(function (Browser $browser) use ($trip) {
            $browser->resize(390, 844)
                ->visit('/t/'.$trip->slug)
                ->waitForText('Sheet Trip')
                // collapsed: price and the opener are visible, the dates are not
                ->assertSee('PHP 1,500')
                ->assertSee('See dates')
                ->assertDontSee('Spot held 24h')
                ->click('@sheet-toggle')
                ->waitForText('Spot held 24h')
                ->assertSee('Guests');
        });
    }

    public function test_the_booking_card_is_fully_expanded_on_desktop(): void
    {
        $trip = $this->publishedTrip();

        $this->browse(function (Browser $browser) use ($trip) {
            $browser->resize(1280, 900)
                ->visit('/t/'.$trip->slug)
                ->waitForText('Sheet Trip')
                // no toggle needed above md — the whole card is always open
                ->assertSee('Spot held 24h')
                ->assertSee('Guests');
        });
    }
}
