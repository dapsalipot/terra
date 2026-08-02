<?php

use App\Services\PhilippineHolidays;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    /*
     * Fresh HTTP factory per test.
     *
     * Pest.php registers a catch-all fake so no test can reach the network, and Laravel
     * evaluates stubs in registration order — so that catch-all would win over the stubs these
     * tests define. Swapping the factory gives each test a clean slate.
     */
    Http::swap(new Illuminate\Http\Client\Factory());
});

function holidayPayload(): array
{
    return [
        ['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day'],
        ['date' => '2026-08-31', 'localName' => 'Araw ng mga Bayani', 'name' => 'National Heroes Day'],
        ['date' => '2026-12-25', 'localName' => 'Pasko', 'name' => 'Christmas Day'],
    ];
}

it('maps holidays to their local names', function () {
    Http::fake(['date.nager.at/*' => Http::response(holidayPayload(), 200)]);

    $holidays = app(PhilippineHolidays::class)->forYear(2026);

    expect($holidays)->toHaveKey('2026-06-12')
        ->and($holidays['2026-06-12'])->toBe('Araw ng Kalayaan')
        ->and($holidays['2026-08-31'])->toBe('Araw ng mga Bayani');
});

it('caches so a page render is not one API call each', function () {
    Http::fake(['date.nager.at/*' => Http::response(holidayPayload(), 200)]);
    $svc = app(PhilippineHolidays::class);

    $svc->forYear(2026);
    $svc->forYear(2026);
    $svc->forYear(2026);

    Http::assertSentCount(1);
});

it('returns nothing rather than exploding when the API is down', function () {
    // A joiner's own bookings must never fail to load because a holiday service is unreachable.
    Http::fake(['date.nager.at/*' => Http::response('', 503)]);

    expect(app(PhilippineHolidays::class)->forYear(2026))->toBe([]);
});

it('survives the API throwing outright', function () {
    Http::fake(fn () => throw new RuntimeException('connection refused'));

    expect(app(PhilippineHolidays::class)->forYear(2026))->toBe([]);
});

it('spans a year boundary, because the grid does', function () {
    // A December grid renders into January, so the range crosses years.
    Http::fake([
        'date.nager.at/api/v3/PublicHolidays/2026/PH' => Http::response([
            ['date' => '2026-12-25', 'localName' => 'Pasko', 'name' => 'Christmas Day'],
        ], 200),
        'date.nager.at/api/v3/PublicHolidays/2027/PH' => Http::response([
            ['date' => '2027-01-01', 'localName' => 'Bagong Taon', 'name' => "New Year's Day"],
        ], 200),
    ]);

    $range = app(PhilippineHolidays::class)->between(
        Carbon\Carbon::parse('2026-11-30'),
        Carbon\Carbon::parse('2027-01-03'),
    );

    expect($range)->toHaveKeys(['2026-12-25', '2027-01-01']);
});

it('leaves out holidays beyond the range asked for', function () {
    Http::fake(['date.nager.at/*' => Http::response(holidayPayload(), 200)]);

    $range = app(PhilippineHolidays::class)->between(
        Carbon\Carbon::parse('2026-08-01'),
        Carbon\Carbon::parse('2026-08-31'),
    );

    expect($range)->toHaveKey('2026-08-31')
        ->and($range)->not->toHaveKey('2026-06-12')
        ->and($range)->not->toHaveKey('2026-12-25');
});

it('ignores an entry with no date', function () {
    Http::fake(['date.nager.at/*' => Http::response([
        ['localName' => 'Nameless', 'name' => 'Nameless'],
        ['date' => '2026-06-12', 'localName' => 'Araw ng Kalayaan', 'name' => 'Independence Day'],
    ], 200)]);

    expect(app(PhilippineHolidays::class)->forYear(2026))->toHaveCount(1);
});
