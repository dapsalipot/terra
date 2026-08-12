<?php

use App\Livewire\Host\Scanner;
use App\Livewire\Reservations\Pass;
use App\Livewire\Reservations\Show;
use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\ReservationService;
use Illuminate\Support\Str;
use Livewire\Livewire;

function passBooking(array $overrides = []): array
{
    $host = User::factory()->create(['role' => 'host', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Pulag hike', 'price' => 2000]);
    $departure = TripDeparture::factory()->for($trip)->create(['capacity' => 10, 'starts_at' => now()->addWeek()]);

    $reservation = Reservation::factory()->for($departure, 'departure')->create(array_merge([
        'joiner_id' => null, 'status' => 'confirmed', 'party_size' => 1,
        'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes', 'contact_email' => 'ada@example.com',
    ], $overrides));

    $participant = ReservationParticipant::factory()->for($reservation)->create([
        'position' => 0, 'first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02',
    ]);

    return [$host, $reservation, $participant, $departure];
}

it('issues a pass only once the host says the money is in', function () {
    [$host, $reservation, $participant] = passBooking();

    expect($participant->fresh()->pass_code)->toBeNull();

    app(ReservationService::class)->markFullyPaid($reservation, $host);

    expect($participant->fresh()->pass_code)->toStartWith('TRA-');
});

it('keeps a pass already saved to somebody phone when the booking is re-tagged', function () {
    [$host, $reservation, $participant] = passBooking();
    $service = app(ReservationService::class);

    $service->markFullyPaid($reservation, $host);
    $code = $participant->fresh()->pass_code;

    $service->markFullyPaid($reservation->fresh(), $host);

    expect($participant->fresh()->pass_code)->toBe($code);
});

it('withdraws the pass when the payment claim or the booking is withdrawn', function () {
    [$host, $reservation, $participant] = passBooking();
    $service = app(ReservationService::class);

    $service->markFullyPaid($reservation, $host);
    $service->unmarkFullyPaid($reservation->fresh());
    expect($participant->fresh()->pass_code)->toBeNull();

    $service->markFullyPaid($reservation->fresh(), $host);
    $service->cancel($reservation->fresh(), 'Cancelled by joiner');
    expect($participant->fresh()->pass_code)->toBeNull();
});

it('checks a traveller in from their code', function () {
    [$host, $reservation, $participant, $departure] = passBooking();
    app(ReservationService::class)->markFullyPaid($reservation, $host);
    $code = $participant->fresh()->pass_code;

    Livewire::actingAs($host)->test(Scanner::class, ['departure' => $departure])
        ->set('code', $code)
        ->call('scan')
        ->assertSet('lastOk', true)
        ->assertSee('Ada Reyes is in.');

    expect($participant->fresh()->checked_in_at)->not->toBeNull()
        ->and($participant->fresh()->checked_in_by)->toBe($host->id);
});

it('refuses a second scan, a foreign pass, and a code from nowhere', function () {
    [$host, $reservation, $participant, $departure] = passBooking();
    app(ReservationService::class)->markFullyPaid($reservation, $host);
    $code = $participant->fresh()->pass_code;

    $service = app(CheckInService::class);
    $service->checkIn($code, $host, $departure->id);

    // already in
    expect(fn () => $service->checkIn($code, $host, $departure->id))
        ->toThrow(RuntimeException::class, 'already checked in');

    // a code nobody issued
    expect(fn () => $service->checkIn('TRA-NOPE', $host, $departure->id))
        ->toThrow(RuntimeException::class, 'not one of ours');

    // somebody else's trip
    [$otherHost] = passBooking();
    expect(fn () => $service->checkIn($code, $otherHost, null))
        ->toThrow(RuntimeException::class, 'another host');
});

it('refuses a pass for a different departure of the same host', function () {
    [$host, $reservation, $participant] = passBooking();
    app(ReservationService::class)->markFullyPaid($reservation, $host);

    $otherDeparture = TripDeparture::factory()
        ->for(Trip::factory()->published()->create(['host_id' => $host->id, 'title' => 'Another trip']))
        ->create(['capacity' => 5, 'starts_at' => now()->addWeeks(2)]);

    expect(fn () => app(CheckInService::class)->checkIn($participant->fresh()->pass_code, $host, $otherDeparture->id))
        ->toThrow(RuntimeException::class, 'not this departure');
});

it('shows the joiner a pass they can open and download', function () {
    [$host, $reservation, $participant] = passBooking();
    app(ReservationService::class)->markFullyPaid($reservation, $host);

    // offered from the booking page
    Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->assertSeeHtml('dusk="open-pass"');

    Livewire::test(Pass::class, ['token' => $reservation->public_token])
        ->assertSee('Ada Reyes')
        ->assertSee($participant->fresh()->pass_code)
        ->assertSeeHtml('dusk="download-pass-'.$participant->id.'"');

    // and the QR itself renders as a PNG for whoever holds the token
    $this->get(route('passes.qr', ['participant' => $participant, 'token' => $reservation->public_token]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

it('keeps the pass page and its QR away from strangers', function () {
    [$host, $reservation, $participant] = passBooking();
    app(ReservationService::class)->markFullyPaid($reservation, $host);

    $this->get(route('passes.qr', $participant))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('passes.qr', $participant))->assertForbidden();
});

it('has no pass page before one is issued', function () {
    [, $reservation] = passBooking();

    $this->get(route('reservations.pass', ['token' => $reservation->public_token]))->assertNotFound();
});

// ──────────────────────────────────────────── group bookings · a tab per traveller

/** A paid group booking, optionally with one traveller added after the money landed. */
function groupPasses(int $party = 3, bool $addLatecomer = false): array
{
    [$host, $reservation, , $departure] = passBooking(['party_size' => $party]);

    for ($i = 1; $i < $party; $i++) {
        ReservationParticipant::factory()->for($reservation)->create([
            'position' => $i, 'first_name' => 'Trav'.$i, 'last_name' => 'Cruz', 'birthday' => '1996-03-03',
        ]);
    }

    app(ReservationService::class)->markFullyPaid($reservation->fresh(), $host);

    $latecomer = null;
    if ($addLatecomer) {
        // Added after the host tagged the money as in, so issueFor() has already run and this
        // one carries no pass until it runs again.
        $latecomer = ReservationParticipant::factory()->for($reservation)->create([
            'position' => $party, 'first_name' => 'Nina', 'last_name' => 'Diaz', 'birthday' => '1994-08-08',
        ]);
    }

    return [$host, $reservation->fresh(), $latecomer, $departure];
}

it('gives a group one tab per traveller, showing one pass at a time', function () {
    [, $reservation] = groupPasses(3);

    $html = Livewire::test(Show::class, ['token' => $reservation->public_token])->html();

    // Three names in the strip, one pass panel — not three stacked QR codes.
    foreach ($reservation->participants as $p) {
        expect($html)->toContain('dusk="pass-tab-'.$p->id.'"');
    }
    expect(substr_count($html, 'dusk="pass-'.$reservation->participants[0]->id.'"'))->toBe(1);
});

it('shows a traveller with no pass yet, but refuses to open them', function () {
    [, $reservation, $latecomer] = groupPasses(2, addLatecomer: true);

    $page = Livewire::test(Show::class, ['token' => $reservation->public_token]);
    $html = $page->html();

    /*
     * They are on the trip, so they are in the strip — dropping them would read as "not coming".
     * What they do not have is a pass, and the tab says so rather than opening an empty panel.
     */
    expect($html)->toContain('dusk="pass-tab-'.$latecomer->id.'"')
        ->and($html)->toContain('Nina')
        ->and($html)->toContain('no pass yet');

    /*
     * And the control is genuinely inert, not merely styled grey. Slicing the whole <button>
     * rather than from the dusk attribute onward: `disabled` is written before it in the
     * markup, so an "after the marker" slice looks in the wrong half of the element.
     */
    $upto = Str::before($html, 'dusk="pass-tab-'.$latecomer->id.'"');
    $tab = Str::afterLast($upto, '<button');

    expect($tab)->toContain('disabled')
        ->and($tab)->toContain('aria-disabled="true"')
        // No wire:click at all — a disabled attribute the server does not honour is decoration.
        ->and($tab)->not->toContain('wire:click');
});

it('will not switch to a traveller who has no pass, however the request arrives', function () {
    [, $reservation, $latecomer] = groupPasses(2, addLatecomer: true);

    // The method is reachable from the browser whatever the markup renders, so `disabled` is
    // decoration until the server says no too.
    $page = Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('setPassTab', $latecomer->id);

    expect($page->get('passTab'))->toBeNull()
        // Still showing a real pass rather than an empty panel.
        ->and($page->html())->toContain($reservation->participants->first()->pass_code);
});

it('switches to the traveller asked for', function () {
    [, $reservation] = groupPasses(3);
    $second = $reservation->participants[1];

    $page = Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('setPassTab', $second->id);

    expect($page->get('passTab'))->toBe($second->id)
        ->and($page->html())->toContain($second->pass_code)
        ->and($page->html())->not->toContain($reservation->participants->first()->pass_code);
});

it('falls back when the remembered traveller loses their pass', function () {
    [$host, $reservation] = groupPasses(2);
    $second = $reservation->participants[1];

    $page = Livewire::test(Show::class, ['token' => $reservation->public_token])
        ->call('setPassTab', $second->id);

    // The host takes the fully-paid tag back, which revokes every pass and re-issues none.
    app(ReservationService::class)->unmarkFullyPaid($reservation->fresh());

    // No passes at all now, so the section goes away rather than rendering a panel around a null.
    $html = Livewire::test(Show::class, ['token' => $reservation->public_token])->html();
    expect($html)->not->toContain('dusk="pass-section"');

    unset($host, $page);
});

it('leaves a solo booking without a tab strip', function () {
    // One traveller, one pass. A tab strip of one is a control that does nothing.
    [$host, $reservation] = passBooking();
    app(ReservationService::class)->markFullyPaid($reservation, $host);

    $html = Livewire::test(Show::class, ['token' => $reservation->fresh()->public_token])->html();

    expect($html)->toContain('dusk="pass-section"')
        ->and($html)->not->toContain('dusk="pass-tabs"');
});
