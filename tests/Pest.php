<?php

use App\Livewire\Host\Trips\Create;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\DuskTestCase;
use Tests\TestCase;

/*
 * Imports stay above every statement in this file, and Pint must not be allowed to reorder them
 * below one. `::class` is resolved against the import table as the file is parsed top to bottom,
 * so a `use Tests\DuskTestCase` sitting underneath this call leaves the name resolving to a
 * global `\DuskTestCase` that does not exist — and the whole suite refuses to boot.
 */
pest()->extend(DuskTestCase::class)
//  ->use(Illuminate\Foundation\Testing\DatabaseMigrations::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        /*
         * No feature test may reach the network.
         *
         * The holiday lookup is the only outbound call in the app, and CACHE_STORE=array means it
         * would otherwise re-fetch on every single test — turning the suite slow, flaky and
         * dependent on a third party being up. Tests that care about holidays override this fake
         * with their own.
         */
        Http::preventStrayRequests();
        Http::fake([
            'date.nager.at/*' => Http::response([], 200),
        ]);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| The trip wizard
|--------------------------------------------------------------------------
|
| For any test that needs a trip made the way a host makes one, or the review step to poke at.
*/

/** A host with a profile, which is all the wizard asks of one. */
function newWizardHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'host'.Str::random(8)]);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

/** The wizard on its review step with a valid form: one stop, a title, a price and `$dates` departures a week apart. */
function wizardAtReview(User $host, int $dates = 1): Testable
{
    $departures = collect(range(1, $dates))
        ->map(fn ($week) => ['starts_at' => now()->addWeeks($week)->toDateString(), 'ends_at' => '', 'capacity' => 9])
        ->all();

    return Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', Place::factory()->create()->id)
        ->call('next')
        ->set('title', 'Review Step Trip')
        ->set('price', '600')
        ->call('next')
        ->call('next')
        ->set('departures', $departures)
        ->call('next')
        ->assertSet('step', Create::LAST_STEP);
}

/** Publish through the wizard as a new host, with `$set` applied on the review step. The trips it made, oldest first. */
function publishViaWizard(array $set = [], int $dates = 1): Collection
{
    $host = newWizardHost();
    $wizard = wizardAtReview($host, $dates);

    foreach ($set as $property => $value) {
        $wizard->set($property, $value);
    }

    $wizard->call('publish');

    return Trip::where('host_id', $host->id)->orderBy('id')->get();
}

/** Open a schedule's edit form as its host, apply `$set` and save. */
function saveTripEdit(Trip $trip, array $set = []): Testable
{
    $form = Livewire::actingAs($trip->host)->test(Create::class, ['trip' => $trip->fresh()]);

    foreach ($set as $property => $value) {
        $form->set($property, $value);
    }

    return $form->call('publish')->assertHasNoErrors();
}
