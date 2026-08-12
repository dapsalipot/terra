<?php

use App\Livewire\Trips\Show;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use App\Services\ReviewService;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function reviewedTrip(): array
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id]);

    $review = Review::create([
        'joiner_id' => User::factory()->create(['name' => 'Ada'])->id,
        'reviewable_type' => Trip::class, 'reviewable_id' => $trip->id,
        'rating' => 2, 'body' => 'The van was late.',
    ]);

    return [$host, $trip, $review];
}

it('lets the host answer a review of their own trip', function () {
    [$host, $trip, $review] = reviewedTrip();

    Livewire::actingAs($host)->test(Show::class, ['trip' => $trip])
        ->assertSeeHtml('dusk="start-reply-'.$review->id.'"')
        ->call('startReply', $review->id)
        ->set('replyBody.'.$review->id, 'You are right — we have changed pickup to 4am.')
        ->call('reply', $review->id)
        ->assertHasNoErrors();

    $review->refresh();

    expect($review->hasReply())->toBeTrue()
        ->and($review->replied_at)->not->toBeNull();
});

it('shows the reply under the review it answers, to everyone', function () {
    [$host, $trip, $review] = reviewedTrip();
    app(ReviewService::class)->reply($review, $host, 'We have changed pickup to 4am.');

    Livewire::test(Show::class, ['trip' => $trip])
        ->assertSeeHtml('dusk="reply-'.$review->id.'"')
        ->assertSee('We have changed pickup to 4am.')
        ->assertSee('Ridgeline replied');
});

it('offers the reply control to nobody but that host', function () {
    [$host, $trip, $review] = reviewedTrip();
    $stranger = User::factory()->create(['role' => 'host', 'handle' => 'other']);

    // not shown
    Livewire::test(Show::class, ['trip' => $trip])
        ->assertDontSeeHtml('dusk="start-reply-'.$review->id.'"');

    Livewire::actingAs($stranger)->test(Show::class, ['trip' => $trip])
        ->assertDontSeeHtml('dusk="start-reply-'.$review->id.'"');

    // and not merely hidden — the service refuses it
    expect(fn () => app(ReviewService::class)->reply($review, $stranger, 'Nonsense'))
        ->toThrow(HttpException::class);
});

it('refuses an empty reply', function () {
    [$host, $trip, $review] = reviewedTrip();

    Livewire::actingAs($host)->test(Show::class, ['trip' => $trip])
        ->call('startReply', $review->id)
        ->set('replyBody.'.$review->id, '   ')
        ->call('reply', $review->id)
        ->assertHasErrors('replyBody.'.$review->id);

    expect($review->fresh()->hasReply())->toBeFalse();
});

it('lets the host correct a reply they already posted', function () {
    [$host, $trip, $review] = reviewedTrip();
    app(ReviewService::class)->reply($review, $host, 'First answer.');

    Livewire::actingAs($host)->test(Show::class, ['trip' => $trip])
        ->assertSee('Edit your reply')
        ->call('startReply', $review->id)
        ->assertSet('replyBody.'.$review->id, 'First answer.')
        ->set('replyBody.'.$review->id, 'Better answer.')
        ->call('reply', $review->id);

    expect($review->fresh()->reply_body)->toBe('Better answer.');
});

it('has nobody with standing to answer a review of a place', function () {
    $place = Place::factory()->create();
    $review = Review::create([
        'joiner_id' => User::factory()->create()->id,
        'reviewable_type' => Place::class, 'reviewable_id' => $place->id,
        'rating' => 4,
    ]);

    expect($review->repliableBy())->toBeNull();
});
