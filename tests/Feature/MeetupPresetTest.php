<?php

use App\Livewire\Host\MeetupPresets;
use App\Models\HostMeetupPreset;
use App\Models\HostProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

function presetHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco']);
    HostProfile::factory()->for($host)->create();

    return $host;
}

it('blocks joiners from the meetup preset page', function () {
    $joiner = User::factory()->create(['role' => 'joiner']);

    $this->actingAs($joiner)->get('/host/meetup-points')->assertForbidden();
});

it('lets a host save a meetup preset', function () {
    $host = presetHost();

    Livewire::actingAs($host)->test(MeetupPresets::class)
        ->set('label', 'Cubao terminal')
        ->set('address', 'EDSA cor. Aurora Blvd')
        ->set('lat', '14.6220')
        ->set('lng', '121.0530')
        ->set('note', 'Look for the blue van')
        ->call('save')
        ->assertHasNoErrors();

    $preset = $host->meetupPresets()->sole();

    expect($preset->label)->toBe('Cubao terminal')
        ->and((float) $preset->lat)->toBe(14.6220);
});

it('only shows a host their own presets', function () {
    $mine = presetHost();
    $other = User::factory()->create(['role' => 'host', 'handle' => 'sunchaser']);
    HostProfile::factory()->for($other)->create();

    HostMeetupPreset::create(['host_id' => $mine->id, 'label' => 'Mine']);
    HostMeetupPreset::create(['host_id' => $other->id, 'label' => 'Theirs']);

    Livewire::actingAs($mine)->test(MeetupPresets::class)
        ->assertSee('Mine')
        ->assertDontSee('Theirs');
});

it('refuses to delete another hosts preset', function () {
    $mine = presetHost();
    $other = User::factory()->create(['role' => 'host', 'handle' => 'sunchaser']);
    $theirs = HostMeetupPreset::create(['host_id' => $other->id, 'label' => 'Theirs']);

    // findOrFail on the host-scoped relation throws rather than returning a 404 response,
    // because the call happens inside a Livewire component method, not an HTTP route.
    try {
        Livewire::actingAs($mine)->test(MeetupPresets::class)->call('delete', $theirs->id);
        $this->fail('A host was able to delete another host’s preset.');
    } catch (ModelNotFoundException) {
        // expected
    }

    expect(HostMeetupPreset::find($theirs->id))->not->toBeNull();
});

it('rejects coordinates outside the valid range with a friendly message', function () {
    $host = presetHost();

    Livewire::actingAs($host)->test(MeetupPresets::class)
        ->set('label', 'Nowhere')
        ->set('lat', '999')
        ->call('save')
        ->assertHasErrors(['lat']);
});
