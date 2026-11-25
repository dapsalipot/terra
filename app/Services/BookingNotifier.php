<?php

namespace App\Services;

use App\Mail\BookingMail;
use App\Mail\PaymentSubmittedMail;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the booking emails, and never lets a send failure undo the thing it is announcing.
 *
 * A confirmation that throws because SMTP is down would roll the host's decision back out of the
 * database, which is far worse than a missing email — the money conversation happens outside this
 * app anyway. Failures are reported and swallowed.
 */
class BookingNotifier
{
    /** Tell the host a receipt is waiting on them. */
    public function notifyHost(Reservation $reservation): void
    {
        $host = $reservation->departure?->trip?->host;

        if (! $host?->email) {
            return;
        }

        try {
            Mail::to($host->email)->send(new PaymentSubmittedMail($reservation));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function send(Reservation $reservation, string $kind, ?string $reason = null): void
    {
        $to = $reservation->recipientEmail();

        if (! $to) {
            return;   // an offline walk-in the host added by hand has nobody to write to
        }

        try {
            Mail::to($to)->send(new BookingMail($reservation, $kind, $reason));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
