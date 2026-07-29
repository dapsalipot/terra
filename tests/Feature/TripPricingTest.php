<?php

use App\Livewire\Host\Trips\Create;
use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

function pricingHost(): User
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'host'.Str::random(8)]);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);

    return $host;
}

/** Publish one schedule with the given price settings. */
function publishPriced(User $host, Place $place, array $set, string $title = 'Priced Trip'): Trip
{
    $test = Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', $title);

    foreach ($set as $key => $value) {
        $test->set($key, $value);
    }

    $test->call('next')->call('next')
        ->set('departures', [['starts_at' => now()->addMonths(3)->toDateString(), 'ends_at' => '', 'capacity' => 10]])
        ->call('next')
        ->call('publish');

    return Trip::where('title', $title)->sole();
}

it('charges the window that covers today', function () {
    $host = pricingHost();
    $place = Place::factory()->create();

    $trip = publishPriced($host, $place, [
        'price' => '2500',
        'priceTiers' => [
            ['label' => 'Early bird', 'amount' => '2000', 'starts_at' => '', 'ends_at' => now()->addWeek()->toDateString()],
            ['label' => 'Regular', 'amount' => '2500', 'starts_at' => now()->addWeeks(1)->addDay()->toDateString(), 'ends_at' => ''],
        ],
    ]);

    expect($trip->priceTiers)->toHaveCount(2)
        ->and($trip->currentTier()->label)->toBe('Early bird')
        ->and($trip->current_price)->toBe(2000.0)
        ->and($trip->nextTier()->label)->toBe('Regular');
});

it('moves to the next window on its own once the early rate expires', function () {
    $host = pricingHost();
    $place = Place::factory()->create();

    $trip = publishPriced($host, $place, [
        'price' => '2500',
        'priceTiers' => [
            ['label' => 'Early bird', 'amount' => '2000', 'starts_at' => '', 'ends_at' => now()->addWeek()->toDateString()],
            ['label' => 'Regular', 'amount' => '2500', 'starts_at' => now()->addWeeks(1)->addDay()->toDateString(), 'ends_at' => ''],
        ],
    ]);

    expect($trip->current_price)->toBe(2000.0);

    // nobody edits anything — the calendar does the work
    $this->travel(2)->weeks();

    expect($trip->fresh()->current_price)->toBe(2500.0)
        ->and($trip->fresh()->currentTier()->label)->toBe('Regular');
});

it('falls back to the flat price when no window covers today', function () {
    $host = pricingHost();
    $place = Place::factory()->create();

    $trip = publishPriced($host, $place, [
        'price' => '2500',
        'priceTiers' => [
            ['label' => 'Flash sale', 'amount' => '1500',
             'starts_at' => now()->addMonth()->toDateString(), 'ends_at' => now()->addMonth()->addDays(3)->toDateString()],
        ],
    ]);

    expect($trip->currentTier())->toBeNull()
        ->and($trip->current_price)->toBe(2500.0);
});

it('has no tiers at all when the host does not add any', function () {
    $host = pricingHost();
    $place = Place::factory()->create();

    $trip = publishPriced($host, $place, ['price' => '2500']);

    expect($trip->priceTiers)->toBeEmpty()
        ->and($trip->current_price)->toBe(2500.0)
        ->and($trip->takesDeposit())->toBeFalse()
        ->and($trip->payable_now)->toBe(2500.0);
});

it('takes a down payment and leaves the balance owing', function () {
    $host = pricingHost();
    $place = Place::factory()->create();

    $trip = publishPriced($host, $place, ['price' => '2500', 'deposit' => '500']);

    expect((float) $trip->deposit_amount)->toBe(500.0)
        ->and($trip->takesDeposit())->toBeTrue()
        ->and($trip->payable_now)->toBe(500.0)
        ->and($trip->current_price - $trip->payable_now)->toBe(2000.0);
});

it('refuses a down payment larger than the price', function () {
    $host = pricingHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', 'Too Much')
        ->set('price', '2000')
        ->set('deposit', '3000')
        ->call('next')
        ->assertHasErrors(['deposit'])
        ->assertSet('step', 2);
});

it('refuses a window that ends before it starts', function () {
    $host = pricingHost();
    $place = Place::factory()->create();

    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', 'Backwards')
        ->set('price', '2000')
        ->set('priceTiers', [[
            'label' => 'Nonsense', 'amount' => '1000',
            'starts_at' => now()->addMonth()->toDateString(),
            'ends_at' => now()->addWeek()->toDateString(),
        ]])
        ->call('next')
        ->assertHasErrors(['priceTiers.0.ends_at'])
        ->assertSet('step', 2);
});

it('gives each schedule its own windows and deposit', function () {
    $host = pricingHost();
    $place = Place::factory()->create();

    // two dates published together
    Livewire::actingAs($host)->test(Create::class)
        ->call('addDestination', $place->id)
        ->call('next')
        ->set('title', 'Two Dates')
        ->set('price', '2500')
        ->set('deposit', '500')
        ->set('priceTiers', [['label' => 'Early bird', 'amount' => '2000', 'starts_at' => '', 'ends_at' => now()->addWeek()->toDateString()]])
        ->call('next')->call('next')
        ->set('departures', [
            ['starts_at' => now()->addMonth()->toDateString(), 'ends_at' => '', 'capacity' => 10],
            ['starts_at' => now()->addMonths(6)->toDateString(), 'ends_at' => '', 'capacity' => 10],
        ])
        ->call('next')
        ->call('publish');

    $trips = Trip::where('title', 'Two Dates')->orderBy('id')->get();

    // both start identical, but the rows are separate so one can be changed alone
    expect($trips)->toHaveCount(2)
        ->and($trips[0]->current_price)->toBe(2000.0)
        ->and($trips[1]->current_price)->toBe(2000.0)
        ->and($trips[0]->priceTiers->pluck('id')->intersect($trips[1]->priceTiers->pluck('id')))->toBeEmpty();

    // drop the far date's early-bird rate; the near one keeps its own
    Livewire::actingAs($host)->test(Create::class, ['trip' => $trips[1]])
        ->call('next')
        ->set('priceTiers', [])
        ->set('deposit', '')
        ->call('next')->call('next')->call('next')
        ->call('publish');

    expect($trips[1]->fresh()->priceTiers)->toBeEmpty()
        ->and($trips[1]->fresh()->current_price)->toBe(2500.0)
        ->and($trips[1]->fresh()->takesDeposit())->toBeFalse()
        ->and($trips[0]->fresh()->current_price)->toBe(2000.0)
        ->and($trips[0]->fresh()->takesDeposit())->toBeTrue();
});

it('shows the live rate and what is owed on the trip page', function () {
    $host = pricingHost();
    $place = Place::factory()->create();

    $trip = publishPriced($host, $place, [
        'price' => '2500',
        'deposit' => '500',
        'priceTiers' => [
            ['label' => 'Early bird', 'amount' => '2000', 'starts_at' => '', 'ends_at' => now()->addWeek()->toDateString()],
            ['label' => 'Regular', 'amount' => '2500', 'starts_at' => now()->addWeeks(1)->addDay()->toDateString(), 'ends_at' => ''],
        ],
    ]);

    Livewire::test(\App\Livewire\Trips\Show::class, ['trip' => $trip])
        ->assertSee('2,000')                    // the live rate, not the flat price
        ->assertSee('Early bird')
        ->assertSee('then PHP 2,500')           // and what it becomes
        ->assertSee('to reserve, rest on the day');
});
