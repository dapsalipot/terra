<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\DuskTestCase;
use Tests\TestCase;

/*
 * Imports stay above every statement in this file, and Pint must not be allowed to reorder them
 * below one. `::class` is resolved against the import table as the file is parsed top to bottom,
 * so a `use Tests\DuskTestCase` sitting underneath this call leaves the name resolving to a
 * global `\DuskTestCase` that does not exist — and the whole suite refuses to boot.
 */
pest()->extend(DuskTestCase::class)
//  ->use(Illuminate\Foundation\Testing\DatabaseMigrations::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        /*
         * No feature test may reach the network.
         *
         * The holiday lookup is the only outbound call in the app, and CACHE_STORE=array means it
         * would otherwise re-fetch on every single test — turning the suite slow, flaky and
         * dependent on a third party being up. Tests that care about holidays override this fake
         * with their own.
         */
        Http::preventStrayRequests();
        Http::fake([
            'date.nager.at/*' => Http::response([], 200),
        ]);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
