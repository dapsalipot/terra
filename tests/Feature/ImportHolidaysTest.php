<?php

use App\Models\Holiday;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Pest.php installs a catch-all fake so no test reaches the network, and Laravel evaluates
    // stubs in registration order — so that catch-all would win over the stubs below. A fresh
    // factory gives each test a clean slate.
    Http::swap(new Illuminate\Http\Client\Factory());
});

function nagerResponse(array $rows): void
{
    Http::fake(['date.nager.at/*' => Http::response($rows, 200)]);
}

it('imports holidays into the table', function () {
    nagerResponse([
        ['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day'],
        ['date' => '2026-12-25', 'localName' => 'Pasko', 'name' => 'Christmas Day'],
    ]);

    $this->artisan('holidays:import', ['year' => 2026])->assertSuccessful();

    expect(Holiday::count())->toBe(2)
        ->and(Holiday::onDate('2026-06-12')->first()->name)->toBe('Araw ng Kalayaan')
        ->and(Holiday::onDate('2026-06-12')->first()->source)->toBe('import');
});

it('is safe to run twice', function () {
    nagerResponse([['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day']]);

    $this->artisan('holidays:import', ['year' => 2026]);
    $this->artisan('holidays:import', ['year' => 2026]);

    expect(Holiday::count())->toBe(1);
});

it('never overwrites a manual correction', function () {
    // A Presidential Proclamation moves a holiday; someone fixes the row by hand. The next
    // scheduled import must not undo that.
    Holiday::create([
        'date' => '2026-06-12',
        'name' => 'Moved by proclamation',
        'source' => 'manual',
    ]);

    nagerResponse([['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day']]);

    $this->artisan('holidays:import', ['year' => 2026])->assertSuccessful();

    expect(Holiday::onDate('2026-06-12')->first()->name)->toBe('Moved by proclamation')
        ->and(Holiday::onDate('2026-06-12')->first()->source)->toBe('manual');
});

it('updates an imported row while leaving a manual one beside it alone', function () {
    Holiday::create(['date' => '2026-06-12', 'name' => 'Hand fixed', 'source' => 'manual']);
    Holiday::create(['date' => '2026-12-25', 'name' => 'Stale name', 'source' => 'import']);

    nagerResponse([
        ['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day'],
        ['date' => '2026-12-25', 'localName' => 'Pasko', 'name' => 'Christmas Day'],
    ]);

    $this->artisan('holidays:import', ['year' => 2026]);

    expect(Holiday::onDate('2026-06-12')->first()->name)->toBe('Hand fixed')
        ->and(Holiday::onDate('2026-12-25')->first()->name)->toBe('Pasko');
});

it('reports a failure instead of wiping the table when the API is down', function () {
    Holiday::create(['date' => '2026-06-12', 'name' => 'Araw ng Kalayaan', 'source' => 'import']);

    Http::fake(['date.nager.at/*' => Http::response('', 503)]);

    $this->artisan('holidays:import', ['year' => 2026])->assertFailed();

    // Last year's good data is better than none.
    expect(Holiday::count())->toBe(1);
});

it('defaults to this year and next when no year is given', function () {
    nagerResponse([['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day']]);

    $this->artisan('holidays:import')->assertSuccessful();

    Http::assertSentCount(2);
});

it('skips a row with no date rather than failing the whole import', function () {
    nagerResponse([
        ['localName' => 'Nameless', 'name' => 'Nameless'],
        ['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day'],
    ]);

    $this->artisan('holidays:import', ['year' => 2026])->assertSuccessful();

    expect(Holiday::count())->toBe(1);
});
