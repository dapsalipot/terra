<?php

use App\Models\Holiday;
use App\Services\PhilippineHolidays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

it('reads holidays from our own table', function () {
    Holiday::create(['date' => '2026-06-12', 'name' => 'Araw ng Kalayaan']);
    Holiday::create(['date' => '2026-08-31', 'name' => 'Araw ng mga Bayani']);

    $holidays = app(PhilippineHolidays::class)->forYear(2026);

    expect($holidays)->toHaveKey('2026-06-12')
        ->and($holidays['2026-08-31'])->toBe('Araw ng mga Bayani');
});

it('makes no network call at all', function () {
    // The whole point of owning the table: a page render must never wait on a third party.
    Http::preventStrayRequests();
    Holiday::create(['date' => '2026-06-12', 'name' => 'Araw ng Kalayaan']);

    expect(app(PhilippineHolidays::class)->forYear(2026))->toHaveCount(1);

    Http::assertNothingSent();
});

it('spans a year boundary, because the grid does', function () {
    Holiday::create(['date' => '2026-12-25', 'name' => 'Pasko']);
    Holiday::create(['date' => '2027-01-01', 'name' => 'Bagong Taon']);

    $range = app(PhilippineHolidays::class)->between(
        Carbon::parse('2026-11-30'),
        Carbon::parse('2027-01-03'),
    );

    expect($range)->toHaveKeys(['2026-12-25', '2027-01-01']);
});

it('leaves out holidays beyond the range asked for', function () {
    Holiday::create(['date' => '2026-06-12', 'name' => 'Araw ng Kalayaan']);
    Holiday::create(['date' => '2026-08-31', 'name' => 'Araw ng mga Bayani']);

    $range = app(PhilippineHolidays::class)->between(
        Carbon::parse('2026-08-01'),
        Carbon::parse('2026-08-31'),
    );

    expect($range)->toHaveKey('2026-08-31')
        ->and($range)->not->toHaveKey('2026-06-12');
});

it('returns nothing for a year nobody has imported', function () {
    expect(app(PhilippineHolidays::class)->forYear(2030))->toBe([]);
});

it('includes a holiday on the last day of the range', function () {
    // whereBetween compares strings against a `Y-m-d H:i:s` serialisation, so the final day fell
    // outside the range. New Year's Eve and 31 December vanished from forYear().
    Holiday::create(['date' => '2026-12-31', 'name' => 'Bisperas ng Bagong Taon']);

    expect(app(PhilippineHolidays::class)->forYear(2026))->toHaveKey('2026-12-31');
});

it('includes a holiday on the first day of the range', function () {
    Holiday::create(['date' => '2026-01-01', 'name' => 'Bagong Taon']);

    expect(app(PhilippineHolidays::class)->forYear(2026))->toHaveKey('2026-01-01');
});
