<?php

use App\Models\HostProfile;
use App\Models\User;
use Illuminate\Support\Facades\Blade;

it('draws the logo as the summit sign mark and the word', function () {
    $html = Blade::render('<x-wordmark />');

    expect($html)
        ->toContain('class="wordmark')
        ->toContain('fill="#BC4B26"')   // the board, which is terracotta on every surface
        ->toContain('font-brand')
        ->toContain('lowercase')        // written "Tarana", shown lowercase
        ->toContain('text-pine')
        ->and(strip_tags($html))->toContain('Tarana');
});

it('has a dark tone and a larger size for the pine surfaces', function () {
    expect(Blade::render('<x-wordmark tone="dark" size="lg" />'))
        ->toContain('text-cream')
        ->toContain('text-[28px]')
        ->not->toContain('text-pine');
});

it('ships the icon set and links it from every layout', function () {
    expect(public_path('favicon.svg'))->toBeFile()
        ->and(public_path('fonts/fredoka.woff2'))->toBeFile()
        // The ICO and PNG are checked by their magic bytes: the favicon used to be an empty file.
        ->and(file_get_contents(public_path('favicon.ico')))->toStartWith("\x00\x00\x01\x00")
        ->and(file_get_contents(public_path('apple-touch-icon.png')))->toStartWith("\x89PNG");

    $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline']);
    HostProfile::factory()->for($host)->create();

    // The public site, the login layout and the host console each have their own <head>.
    foreach ([$this->get('/'), $this->get('/login'), $this->actingAs($host)->get(route('host.dashboard'))] as $response) {
        $response->assertOk()
            ->assertSee('rel="icon"', false)
            ->assertSee('/favicon.svg', false)
            ->assertSee('/apple-touch-icon.png', false)
            ->assertSee('/fonts/fredoka.woff2', false);
    }
});

it('puts the icon in the header of every email', function () {
    expect(Blade::render('<x-mail.layout>hello</x-mail.layout>'))
        ->toContain(url('/apple-touch-icon.png'))
        ->toContain('Tarana');
});
