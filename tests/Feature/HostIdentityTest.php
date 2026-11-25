<?php

use App\Livewire\Host\Profile;
use App\Livewire\Hosts\Index;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\Accents;
use App\Support\HostCompleteness;
use App\Support\Vibes;
use Livewire\Livewire;

function identityHost(array $profile = [], string $handle = 'ridgeline', string $name = 'Ridgeline Club'): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => $handle, 'name' => $name]);
    HostProfile::factory()->for($host)->create(array_merge(['currency' => 'PHP'], $profile));

    return $host;
}

function luminance(string $hex): float
{
    [$r, $g, $b] = array_map(fn ($c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));
    $lin = fn ($v) => $v <= .03928 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4;

    return .2126 * $lin($r) + .7152 * $lin($g) + .0722 * $lin($b);
}

function contrastOf(string $a, string $b): float
{
    [$hi, $lo] = luminance($a) >= luminance($b) ? [luminance($a), luminance($b)] : [luminance($b), luminance($a)];

    return ($hi + .05) / ($lo + .05);
}

it('keeps every accent readable as text on its own tint, on the canvas and on a card', function () {
    foreach (Accents::keys() as $key) {
        $text = Accents::text($key);

        expect(contrastOf($text, Accents::soft($key)))->toBeGreaterThanOrEqual(4.5, "$key on its tint")
            ->and(contrastOf($text, '#FBF9F4'))->toBeGreaterThanOrEqual(4.5, "$key on canvas")
            ->and(contrastOf($text, '#FFFFFF'))->toBeGreaterThanOrEqual(4.5, "$key on white");
    }
});

it('resolves vibes in the order chosen and drops anything no longer in the list', function () {
    expect(Vibes::resolve(['sunrise', 'nope', 'beginner']))
        ->toBe(['sunrise' => 'Sunrise chasers', 'beginner' => 'Beginner friendly']);
});

it('scores a page by how much of it carries personality', function () {
    $empty = ['avatar' => false, 'cover' => false, 'tagline' => '', 'bio' => '', 'location' => '', 'vibes' => 0, 'highlights' => 0, 'links' => 0, 'posts' => 0];

    expect(HostCompleteness::score($empty)['percent'])->toBe(0)
        ->and(HostCompleteness::score(array_merge($empty, ['tagline' => 'Slow hikes', 'vibes' => 2]))['percent'])->toBe(30)
        ->and(HostCompleteness::score(['avatar' => true, 'cover' => true, 'tagline' => 'x', 'bio' => str_repeat('a', 40), 'location' => 'Baguio', 'vibes' => 2, 'highlights' => 2, 'links' => 1, 'posts' => 1])['percent'])->toBe(100);
});

it('saves vibes, reasons to join and a backdrop, dropping blank lines', function () {
    $host = identityHost();

    Livewire::actingAs($host)->test(Profile::class)
        ->set('vibes', ['beginner', 'sunrise'])
        ->set('highlights', ['Small groups', '', 'Hot breakfast'])
        ->set('backdrop', 'contours')
        ->call('save')
        ->assertHasNoErrors();

    $profile = $host->hostProfile->fresh();

    expect($profile->vibes)->toBe(['beginner', 'sunrise'])
        ->and($profile->highlights)->toBe(['Small groups', 'Hot breakfast'])
        ->and($profile->backdrop)->toBe('contours');
});

it('refuses more than four vibes and any that are not in the list', function () {
    $host = identityHost();

    Livewire::actingAs($host)->test(Profile::class)
        ->set('vibes', ['beginner', 'sunrise', 'budget', 'family', 'solo'])
        ->call('save')
        ->assertHasErrors('vibes');

    Livewire::actingAs($host)->test(Profile::class)
        ->set('vibes', ['not-a-vibe'])
        ->call('save')
        ->assertHasErrors('vibes.0');
});

it('draws a host as a storefront card with their promise, vibes, proof and next departure', function () {
    $host = identityHost(['tagline' => 'Slow hikes, big views', 'vibes' => ['beginner', 'small_groups'], 'verified_at' => now(), 'rating' => 4.9]);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'place_id' => Place::factory()->create(['name' => 'Mount Pulag'])->id]);
    TripDeparture::factory()->for($trip)->create(['status' => 'open', 'starts_at' => now()->addWeek(), 'capacity' => 10]);

    Livewire::test(Index::class)
        ->assertSee('Slow hikes, big views')
        ->assertSee('Beginner friendly')
        ->assertSee('Small groups')
        ->assertSee('aria-label="Verified host"', false)
        ->assertSee('Next:')
        ->assertSee('Mount Pulag')
        ->assertSee('Follow');
});

it('lets a signed-in joiner follow and unfollow a host from the card', function () {
    $host = identityHost();
    $joiner = User::factory()->create(['role' => 'joiner']);

    Livewire::actingAs($joiner)->test(Index::class)->call('toggleFollow', $host->id);
    expect($joiner->fresh()->isFollowing($host))->toBeTrue();

    Livewire::actingAs($joiner)->test(Index::class)->call('toggleFollow', $host->id);
    expect($joiner->fresh()->isFollowing($host))->toBeFalse();
});

it('sends a guest to log in rather than following', function () {
    $host = identityHost();

    Livewire::test(Index::class)->call('toggleFollow', $host->id)->assertRedirect(route('login'));
});

it("puts the host's promise, vibes and reasons on the trip page and nobody else's trips", function () {
    $host = identityHost(['tagline' => 'Slow hikes, big views', 'vibes' => ['sunrise'], 'highlights' => ['Hot breakfast at the summit']]);
    $rival = identityHost([], 'rival', 'Rival Outfitters');
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Pulag Sunrise']);
    Trip::factory()->published()->create(['host_id' => $rival->id, 'title' => 'Rival Pulag Trek']);

    $this->get(route('trips.show', $trip))->assertOk()
        ->assertSee('Slow hikes, big views')
        ->assertSee('Sunrise chasers')
        ->assertSee('Hot breakfast at the summit')
        ->assertDontSee('Rival Pulag Trek')
        ->assertDontSee('Rival Outfitters');
});

it("shows a host's vibes, reasons and chosen backdrop in their own colours on their page", function () {
    $host = identityHost(['vibes' => ['photography'], 'highlights' => ['Small groups'], 'backdrop' => 'contours', 'accent' => 'terra']);

    $this->get(route('hosts.show', $host))->assertOk()
        ->assertSee('Photography')
        ->assertSee('Small groups')
        ->assertSee('backdrop-contours', false)
        ->assertSee('--accent-text: #A94524', false);
});
