<?php

use App\Mail\BookingMail;
use App\Models\HostProfile;
use App\Models\Reservation;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\ReservationService;
use App\Support\Ics;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

function inviteBooking(array $attrs = []): Reservation
{
    $host = User::factory()->create(['role' => 'host', 'handle' => 'rl', 'name' => 'Ridgeline']);
    HostProfile::factory()->for($host)->create(['currency' => 'PHP']);
    $trip = Trip::factory()->published()->create([
        'host_id' => $host->id, 'title' => 'Mount Pulag Traverse', 'meetup_point' => 'Baguio terminal',
    ]);
    $departure = TripDeparture::factory()->for($trip)->create([
        'capacity' => 10, 'starts_at' => now()->addWeek()->setTime(5, 0), 'ends_at' => now()->addWeek()->setTime(16, 0),
    ]);

    return Reservation::factory()->for($departure, 'departure')->create(array_merge([
        'joiner_id' => null, 'status' => 'confirmed', 'confirmed_at' => now(), 'party_size' => 1,
        'contact_first_name' => 'Ada', 'contact_last_name' => 'Reyes', 'contact_email' => 'ada@example.com',
    ], $attrs));
}

/** The last message through the (array) transport, as the real MIME structure a client would parse. */
function lastSent(): Email
{
    return app('mail.manager')->mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();
}

/** An ICS body with RFC 5545 line folding undone, so a long property reads as one line. */
function unfolded(string $ics): string
{
    return preg_replace("/\r\n[ \t]/", '', $ics);
}

it('attaches a calendar invitation to the confirmation, typed as an invitation', function () {
    $r = inviteBooking();

    Mail::to('ada@example.com')->send(new BookingMail($r->fresh(), 'confirmed'));

    $attachments = lastSent()->getAttachments();
    expect($attachments)->toHaveCount(1);

    $part = $attachments[0];
    $headers = $part->getPreparedHeaders()->toString();

    /*
     * Both halves. A client acts on the invite only when the method in the Content-Type and the
     * method inside the file agree — either one alone is read as a plain attachment, and the event
     * is never filed.
     */
    expect($headers)->toMatch('/Content-Type: text\/calendar;[^\r\n]*method=REQUEST/i')
        ->and($part->getBody())->toContain('METHOD:REQUEST');
});

it('addresses the invite to whoever the mail went to, from whoever sent it', function () {
    config(['mail.from.address' => 'bookings@terra.test', 'mail.from.name' => 'Terra']);
    $r = inviteBooking();

    Mail::to('ada@example.com')->send(new BookingMail($r->fresh(), 'confirmed'));

    $ics = unfolded(lastSent()->getAttachments()[0]->getBody());

    // An invite naming somebody other than its recipient is not filed, and an organizer that is not
    // the sender reads as spoofed.
    expect($ics)->toContain('mailto:ada@example.com')
        ->and($ics)->toContain('ORGANIZER;CN="Terra":mailto:bookings@terra.test')
        ->and($ics)->toContain('ATTENDEE;')
        ->and($ics)->toContain('RSVP=FALSE');
});

it('reuses the id the feed and the download use, so nothing is added twice', function () {
    $r = inviteBooking();

    $invite = Ics::invite($r->fresh(), 'REQUEST');
    $feed = Ics::single($r->fresh(), 'Terra');

    expect($invite)->toContain('UID:booking-'.$r->public_token.'@terra')
        ->and($feed)->toContain('UID:booking-'.$r->public_token.'@terra');
});

it('takes the trip back out of the calendar when a confirmed booking is cancelled', function () {
    $r = inviteBooking(['status' => 'cancelled', 'cancelled_at' => now()]);

    Mail::to('ada@example.com')->send(new BookingMail($r->fresh(), 'cancelled'));

    $part = lastSent()->getAttachments()[0];

    expect($part->getPreparedHeaders()->toString())->toMatch('/method=CANCEL/i')
        ->and($part->getBody())->toContain('METHOD:CANCEL')
        ->and($part->getBody())->toContain('STATUS:CANCELLED')
        // The same event, so the calendar removes what the confirmation added.
        ->and($part->getBody())->toContain('UID:booking-'.$r->public_token.'@terra');
});

it('sends the cancellation when the joiner cancels a confirmed booking themselves', function () {
    $r = inviteBooking();

    app(ReservationService::class)->cancel($r, 'Cancelled by joiner');

    $part = lastSent()->getAttachments()[0];

    expect($part->getPreparedHeaders()->toString())->toMatch('/method=CANCEL/i')
        ->and($part->getBody())->toContain('METHOD:CANCEL')
        ->and($part->getBody())->toContain('UID:booking-'.$r->public_token.'@terra');
});

it('tells a joiner who cancelled a fully paid booking that money is owed back', function (string $kind) {
    // The refund line used to read what the booking holds *now*, and a cancelled booking holds
    // nothing — so the one joiner owed the most was the one the email said nothing to.
    $r = inviteBooking();
    $service = app(ReservationService::class);

    $service->markFullyPaid($r->fresh(), $r->departure->trip->host);
    $service->cancel($r->fresh(), 'Cancelled by joiner');

    expect($r->fresh()->refund_due)->toBeGreaterThan(0)
        ->and((new BookingMail($r->fresh(), $kind))->render())->toContain('arrange the refund');
})->with(['cancelled', 'departure_cancelled']);

it('does the same when the whole departure is cancelled', function () {
    $r = inviteBooking(['status' => 'cancelled', 'cancelled_at' => now()]);

    Mail::to('ada@example.com')->send(new BookingMail($r->fresh(), 'departure_cancelled'));

    expect(lastSent()->getAttachments()[0]->getBody())->toContain('METHOD:CANCEL');
});

it('does not cancel what was never invited', function () {
    // A request the host never accepted put nothing in anybody's calendar, so a cancellation for
    // it would be a stranger's event vanishing.
    $r = inviteBooking(['status' => 'cancelled', 'cancelled_at' => now(), 'confirmed_at' => null]);

    Mail::to('ada@example.com')->send(new BookingMail($r->fresh(), 'cancelled'));

    expect(lastSent()->getAttachments())->toBeEmpty();
});

it('sends no invite with the mail that is not about the trip being on', function (string $kind) {
    $r = inviteBooking();

    Mail::to('ada@example.com')->send(new BookingMail($r->fresh(), $kind));

    expect(lastSent()->getAttachments())->toBeEmpty();
})->with(['rejected', 'expiring']);

it('points a client that ignores the invite at the file', function () {
    $r = inviteBooking();

    $html = (new BookingMail($r->fresh(), 'confirmed'))->render();

    expect($html)->toContain(route('calendar.booking', ['token' => $r->public_token]));
});

it('writes to the account address, falling back to the one typed at booking', function () {
    $joiner = User::factory()->create(['role' => 'joiner', 'email' => 'account@example.com']);
    $r = inviteBooking(['joiner_id' => $joiner->id]);

    expect($r->fresh()->recipientEmail())->toBe('account@example.com');

    // A guest has no account, so the address typed at booking is the only one there is.
    $r->update(['joiner_id' => null]);

    expect($r->fresh()->recipientEmail())->toBe('ada@example.com');
});
