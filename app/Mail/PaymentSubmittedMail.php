<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** The only booking email addressed to the host rather than the joiner. */
class PaymentSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function build(): self
    {
        $trip = $this->reservation->departure->trip;
        $currency = $trip->host->hostProfile?->currency ?? 'PHP';
        $payment = $this->reservation->latestPayment;

        return $this->subject($this->reservation->displayName().' sent a payment for '.$trip->title)
            ->view('emails.payment-submitted', [
                'hostName' => $trip->host->name,
                'joiner' => $this->reservation->displayName(),
                'tripTitle' => $trip->title,
                'when' => $this->reservation->departure->starts_at->format('D, j M Y'),
                'amount' => $currency.' '.number_format($this->reservation->money()['due']),
                'reference' => $payment?->reference_no,
                'url' => route('host.roster', $this->reservation->departure),
            ]);
    }
}
