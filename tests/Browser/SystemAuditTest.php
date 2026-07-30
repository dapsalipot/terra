<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * A whole-system browser audit.
 *
 * Unlike the feature suites, this collects findings instead of stopping at the first failure —
 * one run is meant to give a full picture. Hard failures (an exception page, a page that will
 * not load at all) still assert, because there is nothing to audit past them.
 *
 * Run visibly with DUSK_HEADLESS_DISABLED=true in .env.dusk.local.
 */
class SystemAuditTest extends DuskTestCase
{
    use DatabaseTruncation;

    /** @var array<string, list<string>> */
    private static array $findings = [];

    private const BEAT = 900;     // pause so a human can actually watch the run

    private function note(string $area, string $finding): void
    {
        self::$findings[$area][] = $finding;
    }

    public static function tearDownAfterClass(): void
    {
        $total = collect(self::$findings)->flatten()->count();

        fwrite(STDERR, "\n".str_repeat('=', 78)."\n");
        fwrite(STDERR, "  SYSTEM AUDIT — {$total} finding(s)\n");
        fwrite(STDERR, str_repeat('=', 78)."\n");

        foreach (self::$findings as $area => $items) {
            fwrite(STDERR, "\n  ".strtoupper($area)."\n");
            foreach ($items as $item) {
                fwrite(STDERR, "    • {$item}\n");
            }
        }

        if ($total === 0) {
            fwrite(STDERR, "\n  Nothing flagged.\n");
        }

        fwrite(STDERR, "\n".str_repeat('=', 78)."\n\n");

        self::$findings = [];

        parent::tearDownAfterClass();
    }

    /**
     * Prove whether this run is actually visible.
     *
     * Headless Chrome reports "HeadlessChrome" in its user agent, so this is the one check that
     * cannot be fooled by sampling processes at the wrong moment.
     */
    public function test_audit_reports_whether_the_browser_is_visible(): void
    {
        $this->browse(function (Browser $browser) {
            $ua = $browser->script('return navigator.userAgent;')[0];
            $headless = str_contains($ua, 'HeadlessChrome');

            fwrite(STDERR, "\n  BROWSER MODE: ".($headless ? 'HEADLESS' : 'VISIBLE')."\n");
            fwrite(STDERR, '  user agent: '.$ua."\n\n");

            if ($headless) {
                $this->note('audit setup', 'Dusk ran HEADLESS — set DUSK_HEADLESS_DISABLED=true in .env.dusk.local');
            }

            $this->assertFalse($headless, 'Dusk is still running headless; the browser will not be visible.');
        });
    }

    // ─────────────────────────────────────────────────────────────── fixtures

    private function seedWorld(): array
    {
        $place = Place::factory()->create(['name' => 'Mount Pulag', 'category' => 'hike']);

        $host = User::factory()->create([
            'role' => 'host', 'handle' => 'trailco', 'name' => 'Trail Co',
            'first_name' => 'Trail', 'last_name' => 'Co',
        ]);
        HostProfile::factory()->for($host)->create([
            'currency' => 'PHP',
            'payment_methods' => [['label' => 'GCash', 'detail' => '0917 123 4567']],
        ]);

        $trip = Trip::factory()->published()->create([
            'host_id' => $host->id, 'place_id' => $place->id,
            'title' => 'Mount Pulag Sunrise Traverse', 'price' => 3500,
        ]);
        $departure = TripDeparture::factory()->for($trip)->create([
            'capacity' => 12, 'starts_at' => now()->addWeeks(3), 'status' => 'open',
        ]);

        $joiner = User::factory()->create([
            'role' => 'joiner', 'name' => 'Mika Ramos',
            'first_name' => 'Mika', 'last_name' => 'Ramos', 'phone' => '09171234567',
        ]);

        return compact('host', 'joiner', 'trip', 'departure', 'place');
    }

    // ─────────────────────────────────────────────────────────────── probes

    /** Severe browser console entries, ignoring noise we cannot control. */
    private function consoleErrors(Browser $browser, string $where): void
    {
        $ignore = ['favicon', 'DevTools', 'chrome-extension', 'Download the React'];

        foreach ($browser->driver->manage()->getLog('browser') as $entry) {
            if (($entry['level'] ?? '') !== 'SEVERE') {
                continue;
            }
            foreach ($ignore as $skip) {
                if (str_contains($entry['message'] ?? '', $skip)) {
                    continue 2;
                }
            }
            $this->note('console errors', $where.' — '.\Illuminate\Support\Str::limit($entry['message'], 160));
        }
    }

    /** Structural accessibility basics that are cheap to check and expensive to miss. */
    private function a11y(Browser $browser, string $where): void
    {
        $result = $browser->script(<<<'JS'
            return (function () {
                const imgs = [...document.querySelectorAll('img')]
                    .filter(i => !i.hasAttribute('alt'));
                const inputs = [...document.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), select, textarea')]
                    .filter(el => !el.labels?.length
                        && !el.getAttribute('aria-label')
                        && !el.getAttribute('aria-labelledby')
                        && !el.getAttribute('placeholder'));
                const buttons = [...document.querySelectorAll('button, a')]
                    .filter(el => !el.textContent.trim()
                        && !el.getAttribute('aria-label')
                        && !el.querySelector('img[alt]:not([alt=""])'));
                return {
                    h1: document.querySelectorAll('h1').length,
                    imgsNoAlt: imgs.length,
                    imgSample: imgs.slice(0, 2).map(i => i.getAttribute('src') || '?'),
                    inputsUnlabelled: inputs.length,
                    inputSample: inputs.slice(0, 4).map(el => (el.tagName.toLowerCase()) + '[' + (el.name || el.id || el.getAttribute('wire:model') || el.type || '?') + ']'),
                    namelessControls: buttons.length,
                    lang: document.documentElement.getAttribute('lang') || '',
                    title: document.title,
                };
            })();
        JS)[0];

        if ($result['h1'] === 0) {
            $this->note('accessibility', "{$where} — no <h1>; screen readers get no page heading");
        } elseif ($result['h1'] > 1) {
            $this->note('accessibility', "{$where} — {$result['h1']} <h1> elements; only one should name the page");
        }

        if ($result['imgsNoAlt'] > 0) {
            $sample = implode(', ', $result['imgSample']);
            $this->note('accessibility', "{$where} — {$result['imgsNoAlt']} img without an alt attribute ({$sample})");
        }

        if ($result['inputsUnlabelled'] > 0) {
            $sample = implode(', ', $result['inputSample']);
            $this->note('accessibility', "{$where} — {$result['inputsUnlabelled']} form field with no label/aria-label ({$sample})");
        }

        if ($result['namelessControls'] > 0) {
            $this->note('accessibility', "{$where} — {$result['namelessControls']} button/link with no accessible name");
        }

        if ($result['lang'] === '') {
            $this->note('accessibility', "{$where} — <html> has no lang attribute");
        }

        if (trim($result['title']) === '') {
            $this->note('accessibility', "{$where} — empty <title>");
        }
    }

    /** A page that scrolls sideways on a phone is broken, not merely untidy. */
    private function noSidewaysScroll(Browser $browser, string $where): void
    {
        $overflow = $browser->script(
            'return document.documentElement.scrollWidth - document.documentElement.clientWidth;'
        )[0];

        if ($overflow > 1) {
            $this->note('mobile layout', "{$where} — page scrolls sideways by {$overflow}px");
        }
    }

    private function noExceptionPage(Browser $browser, string $where): void
    {
        $bad = $browser->script(<<<'JS'
            const t = document.body.innerText || '';
            return /Whoops|SQLSTATE|Undefined variable|Call to a member function|ParseError|syntax error|Class ".*" not found/.test(t);
        JS)[0];

        if ($bad) {
            $this->note('errors', "{$where} — rendered a PHP error/exception page");
        }
    }

    private function sweep(Browser $browser, string $url, string $where, string $expect): void
    {
        $browser->visit($url)->waitForText($expect, 10)->pause(self::BEAT);

        $this->noExceptionPage($browser, $where);
        $this->a11y($browser, $where);
        $this->consoleErrors($browser, $where);
    }

    // ─────────────────────────────────────────────────────────── 1. public

    public function test_audit_public_surface(): void
    {
        $w = $this->seedWorld();

        $this->browse(function (Browser $browser) use ($w) {
            $browser->resize(1440, 900);

            $this->sweep($browser, '/', 'home', 'Better trips with');
            $browser->scrollIntoView('@departing-soon')->pause(self::BEAT);

            $this->sweep($browser, '/trips', 'discovery', 'Mount Pulag Sunrise Traverse');
            $this->sweep($browser, '/hosts', 'hosts index', 'Trail Co');
            $this->sweep($browser, '/trailco', 'host page (handle route)', 'Trail Co');
            $this->sweep($browser, '/t/'.$w['trip']->slug, 'trip detail', 'Mount Pulag Sunrise Traverse');
            $browser->script('window.scrollTo(0, document.body.scrollHeight / 2);');
            $browser->pause(self::BEAT);
            $this->sweep($browser, '/t/'.$w['trip']->slug.'/join', 'registration form', 'Who we contact');
            $this->sweep($browser, '/login', 'login', 'Email me a sign-in link');
            $this->sweep($browser, '/register', 'register', 'Name');
        });

        $this->assertTrue(true, 'public surface swept');
    }

    // ────────────────────────────────────────────────── 2. auth boundaries

    public function test_audit_auth_boundaries(): void
    {
        $w = $this->seedWorld();

        $this->browse(function (Browser $browser) use ($w) {
            $browser->resize(1440, 900);

            // A guest must not reach the host console.
            $browser->visit('/host')->pause(self::BEAT);
            if (! str_contains($browser->driver->getCurrentURL(), '/login')) {
                $this->note('authorization', 'guest reaching /host was not sent to login');
            }

            // A joiner is not a host.
            $browser->loginAs($w['joiner'])->visit('/host')->pause(self::BEAT);
            $code = $browser->script('return document.body.innerText.includes("403") || document.body.innerText.includes("Forbidden");')[0];
            if (! $code && str_contains($browser->driver->getCurrentURL(), '/host')) {
                $this->note('authorization', 'a joiner account loaded the host console');
            }

            // A host must not be able to join a trip as a guest of their own trip.
            $browser->loginAs($w['host'])->visit('/t/'.$w['trip']->slug.'/join')->pause(self::BEAT);
            if (str_contains($browser->driver->getCurrentURL(), '/join')) {
                $this->note('authorization', 'a host account reached the registration form');
            }

            // A booking token belonging to nobody must 404, not leak.
            $browser->visit('/r/00000000-0000-0000-0000-000000000000')->pause(self::BEAT);
            $notFound = $browser->script('return document.body.innerText.includes("404") || document.body.innerText.includes("Not Found");')[0];
            if (! $notFound) {
                $this->note('authorization', 'an unknown booking token did not 404');
            }
        });

        $this->assertTrue(true, 'auth boundaries probed');
    }

    // ──────────────────────────────────────────── 3. the money path, watched

    public function test_audit_the_full_booking_path(): void
    {
        $w = $this->seedWorld();

        $this->browse(function (Browser $browser) use ($w) {
            $browser->resize(1440, 900);

            // Discover the trip the way a stranger would.
            $browser->visit('/trips')
                ->waitForText('Mount Pulag Sunrise Traverse')
                ->pause(self::BEAT)
                ->clickLink('Mount Pulag Sunrise Traverse')
                ->waitForText('Mount Pulag Sunrise Traverse')
                ->pause(self::BEAT)
                ->press('Join this trip')
                ->waitForText('Who we contact about this booking')
                ->pause(self::BEAT);

            // Two seats, booker travelling.
            $browser->clear('@party-size')->type('@party-size', '2')
                ->waitFor('@seat-1')
                ->pause(self::BEAT)
                ->type('@contact-first', 'Ada')
                ->type('@contact-last', 'Reyes')
                ->type('@contact-email', 'ada@example.com')
                ->type('@contact-phone', '09171234567')
                ->keys('@seat-0-birthday', '04', '02', '1998')
                ->pause(self::BEAT)
                ->type('@seat-1-first', 'Ben')
                ->type('@seat-1-last', 'Cruz')
                ->keys('@seat-1-birthday', '01', '10', '1995')
                ->pause(self::BEAT);

            $this->a11y($browser, 'registration form (filled)');
            $this->noExceptionPage($browser, 'registration form (filled)');

            $browser->uncheck('@create-account')
                ->click('@submit-registration')
                ->waitForText('Save this link', 10)
                ->pause(self::BEAT);

            $this->noExceptionPage($browser, 'booking page');
            $this->a11y($browser, 'booking page');

            // The manifest should name both travellers.
            $shows = $browser->script('return document.body.innerText;')[0];
            foreach (['Ada Reyes', 'Ben Cruz'] as $name) {
                if (! str_contains($shows, $name)) {
                    $this->note('booking page', "traveller \"{$name}\" is missing from the manifest");
                }
            }

            // Pay.
            $browser->type('reference_no', 'GC-9021-5567')
                ->pause(self::BEAT)
                ->press('Submit proof of payment')
                ->waitForText('Waiting for', 10)
                ->pause(self::BEAT);

            $this->consoleErrors($browser, 'booking + payment');
        });

        $reservation = Reservation::firstOrFail();

        if ($reservation->status !== 'payment_submitted') {
            $this->note('booking flow', "after paying, status was {$reservation->status}, expected payment_submitted");
        }
        if ($reservation->participants->count() !== 2) {
            $this->note('booking flow', 'two seats booked but '.$reservation->participants->count().' traveller rows written');
        }

        // Host side: does the roster show what the host needs?
        $this->browse(function (Browser $browser) use ($w, $reservation) {
            $browser->loginAs($w['host'])
                ->visit('/host/departures/'.$w['departure']->id.'/roster')
                ->waitForText('Ada Reyes', 10)
                ->pause(self::BEAT);

            $this->noExceptionPage($browser, 'host roster');
            $this->a11y($browser, 'host roster');

            $text = $browser->script('return document.body.innerText;')[0];
            // Case-insensitive: the manifest heading is uppercased by CSS, so innerText differs
            // from the source. A case-sensitive check here reported a defect that did not exist.
            foreach (['Ben Cruz' => 'second traveller', 'travelling' => 'manifest heading'] as $needle => $what) {
                if (! str_contains(strtolower($text), strtolower($needle))) {
                    $this->note('host roster', "{$what} not shown ({$needle})");
                }
            }

            $browser->click('@confirm-'.$reservation->id)
                ->waitForText('Confirmed', 10)
                ->pause(self::BEAT);

            $this->consoleErrors($browser, 'host roster');
        });

        if ($reservation->fresh()->status !== 'confirmed') {
            $this->note('booking flow', 'host confirmation did not move the reservation to confirmed');
        }
    }

    // ─────────────────────────────────────────────── 4. host console sweep

    public function test_audit_host_console(): void
    {
        $w = $this->seedWorld();

        $this->browse(function (Browser $browser) use ($w) {
            $browser->resize(1440, 900)->loginAs($w['host']);

            $pages = [
                '/host' => ['host dashboard', 'Calendar'],
                '/host/trips' => ['host trips', 'My trips'],
                '/host/trips/create' => ['trip wizard', 'Where'],
                '/host/meetup-points' => ['meetup points', 'Meetup'],
                '/host/profile' => ['host profile', 'page'],
            ];

            foreach ($pages as $url => [$where, $expect]) {
                $this->sweep($browser, $url, $where, $expect);
            }
        });

        $this->assertTrue(true, 'host console swept');
    }

    // ──────────────────────────────────────────────────── 5. phone layout

    public function test_audit_phone_layout(): void
    {
        $w = $this->seedWorld();

        $this->browse(function (Browser $browser) use ($w) {
            $browser->resize(375, 812);       // iPhone-class viewport

            $pages = [
                '/' => ['home', 'Better trips with'],
                '/trips' => ['discovery', 'Mount Pulag'],
                '/t/'.$w['trip']->slug => ['trip detail', 'Mount Pulag'],
                '/t/'.$w['trip']->slug.'/join' => ['registration form', 'Who we contact'],
            ];

            foreach ($pages as $url => [$where, $expect]) {
                $browser->visit($url)->waitForText($expect, 10)->pause(self::BEAT);
                $this->noSidewaysScroll($browser, 'phone · '.$where);
                $this->noExceptionPage($browser, 'phone · '.$where);

                // Undersized tap targets are the most common phone defect.
                $small = $browser->script(<<<'JS'
                    // WCAG 2.5.8 (AA) sets 24x24 as the floor and exempts links inline in prose.
                    const inProse = el => !!el.closest('p, li') && getComputedStyle(el).display === 'inline';

                    // A skip link is deliberately 1x1 until focused — measuring it unfocused is
                    // measuring the wrong state, not finding a defect.
                    const srOnly = el => {
                        const r = el.getBoundingClientRect();
                        return r.width <= 1 && r.height <= 1;
                    };

                    // A checkbox inside a padded <label> is hit by tapping the label, so the real
                    // target is the label's box, not the 16px input.
                    const wrappedInLabel = el => {
                        if (el.tagName !== 'INPUT') return false;
                        const lab = el.closest('label');
                        if (!lab) return false;
                        const r = lab.getBoundingClientRect();
                        return r.height >= 24 && r.width >= 24;
                    };

                    return [...document.querySelectorAll('a, button, input[type=checkbox], [role=button]')]
                        .filter(el => {
                            const r = el.getBoundingClientRect();
                            if (r.width === 0 || r.height === 0) return false;   // hidden
                            if (srOnly(el)) return false;                        // skip link
                            if (wrappedInLabel(el)) return false;                // label is the target
                            if (inProse(el)) return false;                       // WCAG exemption
                            return r.height < 24 || r.width < 24;                // real AA failure
                        })
                        .slice(0, 6)
                        .map(el => {
                            const r = el.getBoundingClientRect();
                            const label = (el.getAttribute('aria-label') || el.textContent.trim() || el.tagName).slice(0, 30);
                            return `${label} (${Math.round(r.width)}x${Math.round(r.height)})`;
                        });
                JS)[0];

                foreach ($small as $control) {
                    $this->note('mobile layout', "phone · {$where} — tap target below 24px: {$control}");
                }
            }

            // The host console has to be reachable on a phone.
            $browser->loginAs($w['host'])->visit('/host')->waitForText('Calendar', 10)->pause(self::BEAT);
            $this->noSidewaysScroll($browser, 'phone · host dashboard');
        });

        $this->assertTrue(true, 'phone layout swept');
    }
}
