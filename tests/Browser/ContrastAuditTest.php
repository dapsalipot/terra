<?php

namespace Tests\Browser;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\PaymentSubmission;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * Every visible piece of text, measured against the colour actually behind it.
 *
 * Muted text is written as `text-ink/50` and friends, so the real colour depends on the opacity AND
 * on whatever translucent layers sit behind it — a class name says nothing about whether it passes.
 * This composites every layer down to the page background and compares, the way a contrast checker
 * would, on the pages people actually use, at desktop and phone width.
 *
 * What it cannot see: text over photographs. Where an image sits behind the text there is no flat
 * colour to measure, so those are skipped rather than guessed at (the .glass-dark veil is checked
 * by its own arithmetic).
 */
class ContrastAuditTest extends DuskTestCase
{
    use DatabaseTruncation;

    private const WIDTHS = [[1440, 900], [390, 844]];

    /** @return array{host:User, joiner:User, trip:Trip, open:TripDeparture, soon:TripDeparture, unpaid:Reservation, paid:Reservation, overdue:Reservation, pending:Reservation} */
    private function world(): array
    {
        $host = User::factory()->create(['role' => 'host', 'handle' => 'ridgeline', 'name' => 'Ridgeline']);
        HostProfile::factory()->for($host)->create(['currency' => 'PHP', 'tagline' => 'Cordillera traverses']);
        $joiner = User::factory()->create(['role' => 'joiner', 'name' => 'Mika Ramos']);

        $trip = Trip::factory()->published()->create([
            'host_id' => $host->id, 'title' => 'Mount Pulag Traverse', 'price' => 2000,
            'place_id' => Place::factory()->create(['category' => 'hike'])->id,
            'packing_items' => ['headlamp', 'water'], 'inclusions' => ['Transport', 'Guide'],
            'deposit_amount' => 600, 'meetup_point' => 'Baguio terminal',
        ]);
        $open = TripDeparture::factory()->for($trip)->create(['capacity' => 12, 'starts_at' => now()->addDays(10)]);
        $later = TripDeparture::factory()->for($trip)->create(['capacity' => 12, 'starts_at' => now()->addDays(25)]);

        $make = function (TripDeparture $dep, array $extra = [], ?User $who = null) use ($joiner) {
            $r = Reservation::factory()->for($dep, 'departure')->create(array_merge([
                'joiner_id' => ($who ?? $joiner)->id, 'status' => 'confirmed', 'confirmed_at' => now(), 'party_size' => 1,
                'contact_first_name' => 'Mika', 'contact_last_name' => 'Ramos', 'contact_email' => 'mika@example.com',
            ], $extra));
            ReservationParticipant::factory()->for($r)->create([
                'position' => 0, 'first_name' => 'Mika', 'last_name' => 'Ramos', 'birthday' => '1995-02-11',
            ]);

            return $r;
        };

        $unpaid = $make($open, ['full_payment_due_at' => now()->addDays(4)]);
        PaymentSubmission::factory()->for($unpaid)->create(['reference_no' => 'GC-1', 'method' => 'GCash', 'submitted_at' => now()]);

        $paid = $make($later);
        app(ReservationService::class)->markFullyPaid($paid->fresh(), $host);

        // The states that wear a tinted badge — late (terra) and awaiting a decision (amber) — are the
        // ones a page of settled bookings never draws, and tinted text is where contrast goes wrong.
        $soon = TripDeparture::factory()->for($trip)->create(['capacity' => 12, 'starts_at' => now()->addDays(40)]);
        $overdue = $make($soon, ['full_payment_due_at' => now()->subDays(2)]);

        $pending = $make($open, ['status' => 'pending', 'confirmed_at' => null],
            User::factory()->create(['role' => 'joiner', 'name' => 'Paolo Reyes']));

        return compact('host', 'joiner', 'trip', 'open', 'soon', 'unpaid', 'paid', 'overdue', 'pending');
    }

    /** The measurement. Runs in the page, returns whatever fails. */
    private const PROBE = <<<'JS'
const parse = c => { const p = (c.match(/[\d.]+/g) || []).map(Number); return {r:p[0]||0, g:p[1]||0, b:p[2]||0, a:p.length>3 ? p[3] : 1}; };
const over = (f, b) => ({r:f.r*f.a+b.r*(1-f.a), g:f.g*f.a+b.g*(1-f.a), b:f.b*f.a+b.b*(1-f.a), a:1});
const lin = v => { v /= 255; return v <= .03928 ? v/12.92 : Math.pow((v+.055)/1.055, 2.4); };
const lum = c => .2126*lin(c.r) + .7152*lin(c.g) + .0722*lin(c.b);

// Every translucent layer between the text and the page, composited bottom-up.
const backdrop = el => {
  const layers = []; let n = el;
  while (n && n !== document.documentElement) { const c = parse(getComputedStyle(n).backgroundColor); if (c.a > 0) layers.push(c); n = n.parentElement; }
  const body = parse(getComputedStyle(document.body).backgroundColor);
  layers.push(body.a > 0 ? body : {r:255, g:255, b:255, a:1});
  return layers.reverse().reduce((acc, c) => over(c, acc));
};

// Everything painted as a picture, measured once up front.
const pictures = [];
document.querySelectorAll('body *').forEach(e => {
  if (['IMG','VIDEO','CANVAS','PICTURE'].includes(e.tagName) || getComputedStyle(e).backgroundImage !== 'none') pictures.push(e.getBoundingClientRect());
});

const out = [];
document.querySelectorAll('body *').forEach(el => {
  if (!Array.from(el.childNodes).some(n => n.nodeType === 3 && n.textContent.trim())) return;
  if (el.closest('[disabled],[aria-disabled=true],[aria-hidden=true],script,style,noscript,[x-cloak]')) return;

  const rect = el.getBoundingClientRect();
  if (rect.width < 2 || rect.height < 2 || el.getClientRects().length === 0) return;
  const cs = getComputedStyle(el);
  if (cs.visibility === 'hidden' || cs.display === 'none') return;
  if (cs.clip === 'rect(0px, 0px, 0px, 0px)') return;                 // sr-only

  // Opacity animating in or out (scroll reveals) is not a contrast fact about the design.
  let o = 1, n = el; while (n && n !== document.documentElement) { o *= parseFloat(getComputedStyle(n).opacity); n = n.parentElement; }
  if (o < 0.99) return;

  // Text over a photograph (or a gradient laid on one) has no flat colour behind it to measure.
  // Compared by rectangle rather than by what is under the point, so text below the fold is
  // judged the same way as text in view.
  const cx = rect.left + rect.width/2, cy = rect.top + rect.height/2;
  if (pictures.some(r => cx >= r.left && cx <= r.right && cy >= r.top && cy <= r.bottom)) return;

  const size = parseFloat(cs.fontSize), bold = parseInt(cs.fontWeight) >= 700;
  // A star, a dot or a dash is a graphic rather than something to read, and graphics need 3:1.
  const words = Array.from(el.childNodes).filter(n => n.nodeType === 3).map(n => n.textContent).join('');
  const need = (size >= 24 || (size >= 18.66 && bold) || !/[\p{L}\p{N}]/u.test(words)) ? 3 : 4.5;
  const bg = backdrop(el), fg = over(parse(cs.color), bg);
  const l1 = lum(fg), l2 = lum(bg);
  const ratio = (Math.max(l1, l2) + .05) / (Math.min(l1, l2) + .05);

  if (ratio < need - 0.01) out.push({text: el.textContent.trim().replace(/\s+/g, ' ').slice(0, 40), ratio: Math.round(ratio*100)/100, need, size, cls: (el.getAttribute('class') || '').split(' ').filter(c => /text-/.test(c)).join(' ').slice(0, 60)});
});
const seen = new Set();
return out.filter(f => { const k = f.text + f.cls; if (seen.has(k)) return false; seen.add(k); return true; }).sort((a, b) => a.ratio - b.ratio).slice(0, 12);
JS;

    /** @param array<string, string> $pages label => url */
    private function audit(Browser $browser, array $pages): array
    {
        $failures = [];

        foreach (self::WIDTHS as [$w, $h]) {
            $browser->resize($w, $h);

            foreach ($pages as $label => $url) {
                $browser->visit($url)->pause(700);

                // Scroll-reveal sections sit at opacity 0 until they enter the viewport, and the probe
                // (rightly) ignores anything mid-fade. Reveal them the way scrolling would, or the
                // home page — nearly all of it revealed on scroll — is measured as two lines of text.
                if ($browser->script("const r = document.querySelectorAll('[data-reveal]'); r.forEach(e => e.classList.add('in-view')); return r.length;")[0] > 0) {
                    $browser->pause(1000);
                }

                foreach ($browser->script(self::PROBE)[0] as $f) {
                    $failures[] = sprintf('%s @%d  %.2f:1 (needs %s)  "%s"  [%s]', $label, $w, $f['ratio'], $f['need'], $f['text'], $f['cls']);
                }
            }
        }

        $browser->resize(1920, 1080);

        return $failures;
    }

    private function assertClean(array $failures): void
    {
        $this->assertSame([], $failures, "Text below WCAG AA contrast:\n".implode("\n", $failures));
    }

    public function test_the_public_pages_read_clearly(): void
    {
        $w = $this->world();

        $this->browse(function (Browser $browser) use ($w) {
            $this->assertClean($this->audit($browser, [
                'home' => '/',
                'trips' => '/trips',
                'hosts' => '/hosts',
                'host page' => '/@'.$w['host']->handle,
                'trip' => '/t/'.$w['trip']->slug,
                'join' => '/t/'.$w['trip']->slug.'/join',
                'login' => '/login',
                'register' => '/register',
            ]));
        });
    }

    public function test_a_joiners_pages_read_clearly(): void
    {
        $w = $this->world();

        $this->browse(function (Browser $browser) use ($w) {
            $browser->loginAs($w['joiner']);

            $this->assertClean($this->audit($browser, [
                'my trips' => '/my-trips',
                'my trips: pay' => '/my-trips?panel=pay',
                'booking (owes)' => '/r/'.$w['unpaid']->public_token,
                'booking (paid)' => '/r/'.$w['paid']->public_token,
                'booking (overdue)' => '/r/'.$w['overdue']->public_token,
                'pass page' => '/r/'.$w['paid']->public_token.'/pass',
                'chat' => '/chat/'.$w['open']->id,
            ]));
        });
    }

    public function test_a_hosts_pages_read_clearly(): void
    {
        $w = $this->world();
        $roster = '/host/departures/'.$w['open']->id.'/roster';

        $this->browse(function (Browser $browser) use ($w, $roster) {
            $browser->loginAs($w['host']);

            $this->assertClean($this->audit($browser, [
                'host dashboard' => '/host',
                'host trips' => '/host/trips',
                'new trip' => '/host/trips/create',
                'host profile' => '/host/profile',
                'meetup points' => '/host/meetup-points',
                'roster: money' => $roster,
                'roster: overdue' => '/host/departures/'.$w['soon']->id.'/roster',
                'roster: manifest' => $roster.'?mode=manifest',
                'roster: check-in' => $roster.'?mode=checkin',
                'scanner' => '/host/departures/'.$w['open']->id.'/check-in',
            ]));
        });
    }

    public function test_the_roster_drawer_reads_clearly(): void
    {
        $w = $this->world();

        $this->browse(function (Browser $browser) use ($w) {
            $browser->loginAs($w['host'])->resize(1440, 900)
                ->visit('/host/departures/'.$w['open']->id.'/roster')
                ->waitFor('@open-'.$w['unpaid']->id)
                ->click('@open-'.$w['unpaid']->id)
                ->waitFor('@roster-drawer')
                ->pause(500);

            $failures = [];
            foreach ($browser->script(self::PROBE)[0] as $f) {
                $failures[] = sprintf('drawer  %.2f:1 (needs %s)  "%s"  [%s]', $f['ratio'], $f['need'], $f['text'], $f['cls']);
            }

            $this->assertClean($failures);
        });
    }

    public function test_the_admin_console_reads_clearly(): void
    {
        $this->world();
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Moderator']);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin);

            $this->assertClean($this->audit($browser, ['admin' => '/admin', 'admin hosts' => '/admin?tab=hosts']));
        });
    }
}
