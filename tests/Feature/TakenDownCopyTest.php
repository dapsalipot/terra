<?php

use App\Livewire\Host\Trips\Create;
use App\Livewire\Host\Trips\Index;
use App\Models\Trip;
use App\Services\ModerationService;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * A host keeps a taken-down trip and may go on editing it, but must not put it back up by copying it.
 */

/** Open a copy of `$trip` and give it the one thing a copy lacks, a date. The change saves a draft. */
function startTripCopy(Trip $trip): Testable
{
    return Livewire::actingAs($trip->host)->test(Create::class, ['from' => $trip])
        ->set('departures', [['starts_at' => now()->addMonth()->toDateString(), 'ends_at' => '', 'capacity' => 9]]);
}

it('will not open a copy of a taken-down trip', function () {
    $trip = publishViaWizard()->sole();
    app(ModerationService::class)->unpublish($trip);

    // "Add another schedule", the "Run one of these again?" chips and a typed address all arrive here
    $this->actingAs($trip->host)->get(route('host.trips.duplicate', $trip))->assertForbidden();
});

it('still publishes a copy of a live trip', function () {
    $trip = publishViaWizard()->sole();

    startTripCopy($trip)->call('publish')->assertHasNoErrors();

    expect(Trip::published()->count())->toBe(2);
});

it('will not publish a copy that was already open when the trip was taken down', function () {
    $trip = publishViaWizard()->sole();
    $copy = startTripCopy($trip);
    app(ModerationService::class)->unpublish($trip);

    $copy->call('publish');

    expect(Trip::published()->count())->toBe(0);
    $copy->assertHasErrors(['copiedFromId']);
});

it('will not publish a saved draft of a copy once its source is taken down', function () {
    $trip = publishViaWizard()->sole();
    startTripCopy($trip);
    app(ModerationService::class)->unpublish($trip);

    $draft = Livewire::actingAs($trip->host)->test(Create::class)
        ->assertSet('draftRestored', true)
        ->call('goToStep', Create::LAST_STEP)
        ->call('publish');

    expect(Trip::published()->count())->toBe(0);
    $draft->assertHasErrors(['copiedFromId'])->assertSee('has been taken down');
});

it('does not let the browser change what a form is editing or was copied from', function (string $property) {
    $trip = publishViaWizard()->sole();
    $form = Livewire::actingAs($trip->host)->test(Create::class, ['trip' => $trip]);

    // clearing either one would send a taken-down trip's form up as a new, live trip
    expect(fn () => $form->set($property, null))->toThrow(CannotUpdateLockedPropertyException::class);
})->with(['editingId', 'copiedFromId']);

it('offers only trips that are still up as something to copy', function () {
    [$down, $live] = publishViaWizard(dates: 2)->all();
    app(ModerationService::class)->unpublish($down);

    // "Add another schedule" under the offering on My trips
    Livewire::actingAs($down->host)->test(Index::class)
        ->assertSeeHtml('href="'.route('host.trips.duplicate', $live).'"')
        ->assertDontSeeHtml('href="'.route('host.trips.duplicate', $down).'"');

    // "Run one of these again?" on a new trip
    Livewire::actingAs($down->host)->test(Create::class)
        ->assertSeeHtml('dusk="reuse-'.$live->id.'"')
        ->assertDontSeeHtml('dusk="reuse-'.$down->id.'"');
});

it('offers no copy at all of an offering whose only schedule is down', function () {
    $trip = publishViaWizard()->sole();
    Livewire::actingAs($trip->host)->test(Index::class)->assertSee('Add another schedule');

    app(ModerationService::class)->unpublish($trip);

    Livewire::actingAs($trip->host)->test(Index::class)->assertDontSee('Add another schedule');
});
