<?php

use App\Livewire\Host\Profile;
use App\Livewire\Hosts\Show;
use App\Models\HostProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function profileHost(array $profile = []): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'trailco', 'name' => 'Trail Collective']);
    HostProfile::factory()->for($host)->create($profile);

    return $host;
}

it('blocks joiners from the profile settings page', function () {
    $joiner = User::factory()->create(['role' => 'joiner']);

    $this->actingAs($joiner)->get('/host/profile')->assertForbidden();
});

it('lets a host open their profile settings prefilled', function () {
    $host = profileHost(['tagline' => 'Sunrise treks', 'location' => 'Baguio', 'accent' => 'terra']);

    Livewire::actingAs($host)->test(Profile::class)
        ->assertSet('name', 'Trail Collective')
        ->assertSet('handle', 'trailco')
        ->assertSet('tagline', 'Sunrise treks')
        ->assertSet('location', 'Baguio')
        ->assertSet('accent', 'terra');
});

it('saves identity, look and links', function () {
    $host = profileHost();

    Livewire::actingAs($host)->test(Profile::class)
        ->set('name', 'Trail Co.')
        ->set('handle', 'trailco2')
        ->set('tagline', 'Small groups. Big mountains.')
        ->set('location', 'Sagada')
        ->set('bio', 'We run weekend traverses.')
        ->set('accent', 'amber')
        ->set('links.instagram', '@trailco')
        ->call('save')
        ->assertHasNoErrors();

    $host->refresh();

    expect($host->name)->toBe('Trail Co.')
        ->and($host->handle)->toBe('trailco2')
        ->and($host->hostProfile->tagline)->toBe('Small groups. Big mountains.')
        ->and($host->hostProfile->accent)->toBe('amber')
        ->and($host->hostProfile->links['instagram'])->toBe('@trailco');
});

it('rejects an accent outside the allow-listed palette', function () {
    $host = profileHost();

    Livewire::actingAs($host)->test(Profile::class)
        ->set('accent', '#ff00ff')
        ->call('save')
        ->assertHasErrors(['accent']);

    expect($host->hostProfile->fresh()->accent)->not->toBe('#ff00ff');
});

it('rejects a handle already taken by another host', function () {
    profileHost();                                    // owns @trailco
    $other = User::factory()->create(['role' => 'host', 'handle' => 'sunchaser']);
    HostProfile::factory()->for($other)->create();

    Livewire::actingAs($other)->test(Profile::class)
        ->set('handle', 'trailco')
        ->call('save')
        ->assertHasErrors(['handle']);
});

it('stores avatar and cover uploads on the public disk', function () {
    Storage::fake('public');
    $host = profileHost();

    Livewire::actingAs($host)->test(Profile::class)
        ->set('avatar', UploadedFile::fake()->image('me.jpg'))
        ->set('cover', UploadedFile::fake()->image('ridge.jpg', 1600, 600))
        ->call('save')
        ->assertHasNoErrors();

    $host->refresh();

    expect($host->avatar)->not->toBeNull()
        ->and($host->hostProfile->cover_photo)->not->toBeNull();

    Storage::disk('public')->assertExists($host->avatar);
    Storage::disk('public')->assertExists($host->hostProfile->cover_photo);
});

it('stores an e-wallet QR against its payment method', function () {
    Storage::fake('public');
    $host = profileHost();

    Livewire::actingAs($host)->test(Profile::class)
        ->set('payment_methods', [
            ['label' => 'GCash', 'detail' => '0917 000 0000', 'qr' => null],
            ['label' => 'Bank transfer', 'detail' => '•••• 4821', 'qr' => null],
        ])
        ->set('payment_qr.0', UploadedFile::fake()->image('gcash-qr.png'))
        ->call('save')
        ->assertHasNoErrors();

    $methods = $host->hostProfile->fresh()->payment_methods;

    expect($methods[0]['qr'])->not->toBeNull()
        // the QR belongs to the method it was uploaded against, not to all of them
        ->and($methods[1]['qr'] ?? null)->toBeNull();

    Storage::disk('public')->assertExists($methods[0]['qr']);
});

it('removes a QR without deleting the payment method', function () {
    Storage::fake('public');
    $host = profileHost();

    $component = Livewire::actingAs($host)->test(Profile::class)
        ->set('payment_methods', [['label' => 'GCash', 'detail' => '0917 000 0000', 'qr' => null]])
        ->set('payment_qr.0', UploadedFile::fake()->image('qr.png'))
        ->call('save');

    $stored = $host->hostProfile->fresh()->payment_methods[0]['qr'];

    $component->call('removeQr', 0)->call('save');

    $methods = $host->hostProfile->fresh()->payment_methods;

    expect($methods)->toHaveCount(1)
        ->and($methods[0]['label'])->toBe('GCash')
        ->and($methods[0]['qr'])->toBeNull();

    Storage::disk('public')->assertMissing($stored);
});

it('drops payment methods left blank', function () {
    $host = profileHost();

    Livewire::actingAs($host)->test(Profile::class)
        ->set('payment_methods', [
            ['label' => 'GCash', 'detail' => '0917 000 0000'],
            ['label' => '', 'detail' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($host->hostProfile->fresh()->payment_methods)->toHaveCount(1);
});

it('shows the tagline and tabs on the public page', function () {
    $host = profileHost(['tagline' => 'Above the clouds', 'location' => 'Benguet']);

    $this->get('/@trailco')->assertOk()
        ->assertSee('Above the clouds')
        ->assertSee('Benguet')
        ->assertSee('Trips')
        ->assertSee('Wall')
        ->assertSee('About');
});

it('lets the owner post multiple photos to the wall and delete them', function () {
    Storage::fake('public');
    $host = profileHost();

    $component = Livewire::actingAs($host)->test(Show::class, ['user' => $host])
        ->set('postBody', 'Ridge before sunrise.')
        ->set('postImages', [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.jpg'),
            UploadedFile::fake()->image('three.jpg'),
        ])
        ->call('publishPost')
        ->assertHasNoErrors()
        ->assertSet('tab', 'wall');

    $post = $host->posts()->sole();
    $paths = $post->images->pluck('path');

    expect($paths)->toHaveCount(3)
        // position must be stable so the carousel order matches what the host uploaded
        ->and($post->images->pluck('position')->all())->toBe([0, 1, 2]);

    $paths->each(fn ($path) => Storage::disk('public')->assertExists($path));

    $component->call('deletePost', $post->id);

    expect($host->posts()->count())->toBe(0);
    $paths->each(fn ($path) => Storage::disk('public')->assertMissing($path));
});

it('drops a staged image before publishing', function () {
    $host = profileHost();

    Livewire::actingAs($host)->test(Show::class, ['user' => $host])
        ->set('postImages', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])
        ->call('removeStagedImage', 0)
        ->assertCount('postImages', 1);
});

it('does not let a stranger post to someone elses wall', function () {
    $host = profileHost();
    $stranger = User::factory()->create(['role' => 'joiner']);

    Livewire::actingAs($stranger)->test(Show::class, ['user' => $host])
        ->set('postBody', 'not mine')
        ->call('publishPost')
        ->assertForbidden();
});
