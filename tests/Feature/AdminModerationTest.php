<?php

use App\Livewire\Admin\Dashboard;
use App\Livewire\Trips\Show;
use App\Models\HostProfile;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use App\Services\ReviewService;
use Livewire\Livewire;

function admin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Staff']);
}

function moderatedHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

it('hides the admin area from everyone who is not staff', function () {
    // 404, not 403: a 403 confirms the area exists.
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create(['role' => 'joiner']))
        ->get(route('admin.dashboard'))->assertNotFound();

    $this->actingAs(moderatedHost())->get(route('admin.dashboard'))->assertNotFound();

    $this->actingAs(admin())->get(route('admin.dashboard'))->assertOk();
});

it('takes a trip out of discovery without touching its bookings', function () {
    $host = moderatedHost();
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Pulag hike']);

    Livewire::actingAs(admin())->test(Dashboard::class)
        ->assertSeeHtml('dusk="unpublish-'.$trip->id.'"')
        ->call('unpublish', $trip->id);

    expect($trip->fresh()->status)->toBe('archived')
        ->and(Trip::published()->pluck('id'))->not->toContain($trip->id);

    // and it goes back
    Livewire::actingAs(admin())->test(Dashboard::class)->call('republish', $trip->id);
    expect(Trip::published()->pluck('id'))->toContain($trip->id);
});

it('drops a suspended host out of discovery but leaves their bookings reachable', function () {
    $host = moderatedHost();
    $trip = Trip::factory()->published()->create(['host_id' => $host->id]);

    Livewire::actingAs(admin())->test(Dashboard::class)
        ->set('tab', 'hosts')
        ->call('suspend', $host->id);

    expect($host->fresh()->isSuspended())->toBeTrue()
        ->and(Trip::published()->pluck('id'))->not->toContain($trip->id)
        // suspension is not deletion — the trip and its record survive
        ->and($trip->fresh()->status)->toBe('published');

    Livewire::actingAs(admin())->test(Dashboard::class)->call('unsuspend', $host->id);
    expect(Trip::published()->pluck('id'))->toContain($trip->id);
});

it('will not let staff suspend staff from the moderation desk', function () {
    $other = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs(admin())->test(Dashboard::class)
        ->call('suspend', $other->id)
        ->assertForbidden();

    expect($other->fresh()->isSuspended())->toBeFalse();
});

it('stops a hidden review counting toward the rating it moved', function () {
    $host = moderatedHost();
    $trip = Trip::factory()->published()->create(['host_id' => $host->id]);

    Review::create(['joiner_id' => User::factory()->create()->id, 'reviewable_type' => Trip::class,
        'reviewable_id' => $trip->id, 'rating' => 5, 'body' => 'Wonderful']);
    $abusive = Review::create(['joiner_id' => User::factory()->create()->id, 'reviewable_type' => Trip::class,
        'reviewable_id' => $trip->id, 'rating' => 1, 'body' => 'Abusive words']);

    app(ReviewService::class);
    expect(Review::visible()->count())->toBe(2);

    Livewire::actingAs(admin())->test(Dashboard::class)
        ->set('tab', 'reviews')
        ->call('hideReview', $abusive->id);

    expect($abusive->fresh()->hidden_at)->not->toBeNull()
        ->and(Review::visible()->count())->toBe(1)
        // kept, not deleted — the words survive the argument that hid them
        ->and(Review::count())->toBe(2);

    Livewire::actingAs(admin())->test(Dashboard::class)->call('unhideReview', $abusive->id);
    expect(Review::visible()->count())->toBe(2);
});

it('keeps a hidden review off the public trip page', function () {
    $host = moderatedHost();
    $trip = Trip::factory()->published()->create(['host_id' => $host->id]);

    Review::create(['joiner_id' => User::factory()->create()->id, 'reviewable_type' => Trip::class,
        'reviewable_id' => $trip->id, 'rating' => 1, 'body' => 'Hidden words here',
        'hidden_at' => now()]);

    Livewire::test(Show::class, ['trip' => $trip])
        ->assertDontSee('Hidden words here');
});

it('records which member of staff made each decision', function () {
    $staff = admin();
    $host = moderatedHost();
    $trip = Trip::factory()->published()->create(['host_id' => $host->id]);
    $review = Review::create(['joiner_id' => User::factory()->create()->id, 'reviewable_type' => Trip::class,
        'reviewable_id' => $trip->id, 'rating' => 1, 'body' => 'Words']);

    Livewire::actingAs($staff)->test(Dashboard::class)
        ->call('suspend', $host->id)
        ->call('hideReview', $review->id);

    // "Who decided this?" is the first question asked when a decision is challenged.
    expect($host->fresh()->suspended_by)->toBe($staff->id)
        ->and($review->fresh()->hidden_by)->toBe($staff->id);

    Livewire::actingAs($staff)->test(Dashboard::class)
        ->call('unsuspend', $host->id)
        ->call('unhideReview', $review->id);

    expect($host->fresh()->suspended_by)->toBeNull()
        ->and($review->fresh()->hidden_by)->toBeNull();
});
