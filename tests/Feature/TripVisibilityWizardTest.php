<?php

use App\Livewire\Host\Trips\Create;
use App\Models\Trip;
use Livewire\Livewire;

/*
 * Who can find a trip, chosen on the wizard's review step. Trips here are made through the wizard
 * (publishViaWizard) so an edit starts from a form that validates, the way a real schedule does.
 */

it('defaults to everyone', fn () => expect(publishViaWizard()->first()->visibility)->toBe('public'));

it('gives every schedule in a group the same choice and the same opening date', function () {
    $trips = publishViaWizard(['visibility' => 'followers', 'followersWindow' => '1w'], dates: 2);

    expect($trips)->toHaveCount(2)
        ->and($trips->pluck('visibility')->unique()->all())->toBe(['followers'])
        ->and($trips->pluck('public_at')->unique())->toHaveCount(1)
        // one shared date that is really a week out, not two matching nulls
        ->and($trips->first()->public_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()))->toBeTrue();
});

it('clears public_at for link only', function () {
    // a window picked and then abandoned must not leak onto a choice that has no window
    $trip = publishViaWizard(['followersWindow' => '1w', 'visibility' => 'link'])->sole();

    expect($trip->visibility)->toBe('link')->and($trip->public_at)->toBeNull();
});

it('keeps the opening date on "keep" and restarts it on a preset', function () {
    $trip = publishViaWizard(['visibility' => 'followers', 'followersWindow' => '1w'])->sole();
    $host = $trip->host;
    $opensAt = $trip->public_at;

    Livewire::actingAs($host)->test(Create::class, ['trip' => $trip])
        ->assertSet('visibility', 'followers')
        ->assertSet('followersWindow', 'keep')
        ->call('publish');

    expect($trip->fresh()->public_at->equalTo($opensAt))->toBeTrue();

    Livewire::actingAs($host)->test(Create::class, ['trip' => $trip])
        ->set('followersWindow', '1d')
        ->call('publish');

    expect($trip->fresh()->public_at->between(now()->addHours(23), now()->addHours(25)))->toBeTrue();
});

it('edits one schedule without touching its siblings', function () {
    [$first, $second] = publishViaWizard(dates: 2)->all();

    Livewire::actingAs($first->host)->test(Create::class, ['trip' => $first])
        ->set('visibility', 'link')
        ->call('publish');

    expect($first->fresh()->visibility)->toBe('link')
        ->and($second->fresh()->visibility)->toBe('public');
});

it('saves an effectively-public trip as everyone with no public_at', function () {
    $trip = publishViaWizard(['visibility' => 'followers', 'followersWindow' => '1d'])->sole();
    $trip->update(['public_at' => now()->subDay()]);   // the window has passed, so listings have read it as public since

    Livewire::actingAs($trip->host)->test(Create::class, ['trip' => $trip])
        ->assertSet('visibility', 'public')
        ->call('publish');

    expect($trip->fresh()->visibility)->toBe('public')->and($trip->fresh()->public_at)->toBeNull();
});

it('restores the choice from a draft and inherits it on duplicate', function () {
    $host = newWizardHost();

    Livewire::actingAs($host)->test(Create::class)->set('visibility', 'followers')->set('followersWindow', '1w')->call('saveDraft');
    Livewire::actingAs($host)->test(Create::class)->assertSet('visibility', 'followers')->assertSet('followersWindow', '1w');

    $followersTrip = Trip::factory()->published()->followersFirst()->create(['host_id' => $host->id]);
    Livewire::actingAs($host)->test(Create::class, ['from' => $followersTrip])
        ->assertSet('visibility', 'followers')
        ->assertSet('followersWindow', '3d')
        ->assertSet('currentOpensAt', null, strict: true);

    $linkTrip = Trip::factory()->published()->linkOnly()->create(['host_id' => $host->id]);
    Livewire::actingAs($host)->test(Create::class, ['from' => $linkTrip])->assertSet('visibility', 'link');

    // a followers-first window that has passed is public, and a copy starts from what listings show now
    $opened = Trip::factory()->published()->followersFirst(now()->subDay())->create(['host_id' => $host->id]);
    Livewire::actingAs($host)->test(Create::class, ['from' => $opened])->assertSet('visibility', 'public');
});

it('rejects an unknown visibility', function () {
    wizardAtReview(newWizardHost())->set('visibility', 'everybody')->call('publish')->assertHasErrors(['visibility']);

    expect(Trip::count())->toBe(0);
});

it('rejects an unknown followers window', function () {
    wizardAtReview(newWizardHost())->set('visibility', 'followers')->set('followersWindow', '2w')->call('publish')->assertHasErrors(['followersWindow']);

    expect(Trip::count())->toBe(0);
});

it('falls back to three days when "keep" has no opening date to keep', function () {
    // a new trip has none
    $new = publishViaWizard(['visibility' => 'followers', 'followersWindow' => 'keep'])->sole();

    // and neither does a schedule that was not followers first
    $public = publishViaWizard()->sole();
    Livewire::actingAs($public->host)->test(Create::class, ['trip' => $public])
        ->set('visibility', 'followers')
        ->set('followersWindow', 'keep')
        ->call('publish');

    expect($new->public_at->between(now()->addHours(71), now()->addHours(73)))->toBeTrue()
        ->and($public->fresh()->public_at->between(now()->addHours(71), now()->addHours(73)))->toBeTrue();
});

it('offers the three choices on the review step and a window only for followers first', function () {
    wizardAtReview(newWizardHost())
        ->assertSee('Who can see this trip')
        ->assertSeeHtml(['dusk="visibility-public"', 'dusk="visibility-followers"', 'dusk="visibility-link"'])
        ->assertDontSeeHtml('dusk="followers-window"')
        ->set('visibility', 'followers')
        ->assertSeeHtml([
            'dusk="followers-window"',
            '<option value="1d">1 day</option>', '<option value="3d">3 days</option>', '<option value="1w">1 week</option>',
        ])
        ->assertDontSee('Keep: opens')
        ->set('visibility', 'link')
        ->assertDontSeeHtml('dusk="followers-window"');
});

it('offers to keep the opening date only while editing a followers-first schedule', function () {
    $this->travelTo('2026-11-26 09:00:00');
    $trip = publishViaWizard(['visibility' => 'followers', 'followersWindow' => '1w'])->sole();
    $trip->update(['public_at' => '2026-12-05 18:00:00']);

    Livewire::actingAs($trip->host)->test(Create::class, ['trip' => $trip])
        ->assertSet('currentOpensAt', '5 Dec, 6:00 pm')
        ->call('goToStep', 5)
        ->assertSeeHtml('<option value="keep">Keep: opens 5 Dec, 6:00 pm</option>');

    Livewire::actingAs($trip->host)->test(Create::class, ['from' => $trip])
        ->assertSet('currentOpensAt', null, strict: true)
        ->call('goToStep', 5)
        ->assertDontSee('Keep: opens');
});
