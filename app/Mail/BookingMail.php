<?php

namespace App\Mail;

use App\Models\Reservation;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Every booking email in one mailable.
 *
 * Five separate Mailable classes would each repeat the same job: pull the trip off the booking,
 * format the money in the host's currency, build the token link. The kind is a parameter because
 * that is the only thing that actually differs between them.
 */
class BookingMail extends Mailable
{
    use Queueable, SerializesModels;

    /** kind => [subject template, view] */
    private const KINDS = [
        'confirmed' => ['%s confirmed your spot', 'emails.booking-confirmed'],
        'rejected' => ['%s could not verify your payment', 'emails.payment-rejected'],
        'expiring' => ['Your seat on %s is about to be released', 'emails.hold-expiring'],
        'departure_cancelled' => ['%s has been cancelled', 'emails.departure-cancelled'],
        'cancelled' => ['Your booking on %s was cancelled', 'emails.booking-cancelled'],
    ];

    public function __construct(
        public Reservation $reservation,
        public string $kind,
        public ?string $reason = null,
    ) {}

    public function build(): self
    {
        $trip = $this->reservation->departure->trip;
        $host = $trip->host;
        $money = $this->reservation->money();
        $currency = $host->hostProfile?->currency ?? 'PHP';
        $fmt = fn (float $n) => $currency.' '.number_format($n);

        [$subject, $view] = self::KINDS[$this->kind] ?? self::KINDS['confirmed'];

        // The subject's %s is the host for host-driven news and the trip for everything else —
        // "Ridgeline confirmed your spot" reads better than the trip title there, and
        // "Pulag hike has been cancelled" reads better than the host.
        $subjectSubject = in_array($this->kind, ['confirmed', 'rejected'], true) ? $host->name : $trip->title;

        return $this->subject(sprintf($subject, $subjectSubject))
            ->view($view, [
                'name' => $this->reservation->contactName() ?: $this->reservation->displayName(),
                'hostName' => $host->name,
                'tripTitle' => $trip->title,
                'when' => $this->reservation->departure->starts_at->format('D, j M Y'),
                'meetup' => $trip->meetup_point,
                'due' => $money['due'] > 0 ? $fmt($money['due']) : 0,
                'paid' => $money['paid'] > 0 ? $fmt($money['paid']) : 0,
                'reason' => $this->reason,
                'remaining' => $this->reservation->expires_at
                    ? $this->reservation->expires_at->diffForHumans(['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1])
                    : 'a short while',
                'url' => route('reservations.show', ['token' => $this->reservation->public_token]),
            ]);
    }
}
