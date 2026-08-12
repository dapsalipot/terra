<?php

namespace App\Mail;

use App\Models\WaitlistEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SeatOpenedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WaitlistEntry $entry) {}

    public function build(): self
    {
        $departure = $this->entry->departure;
        $trip = $departure->trip;

        return $this->subject('A seat opened on '.$trip->title)
            ->view('emails.seat-opened', [
                'name' => $this->entry->name,
                'tripTitle' => $trip->title,
                'hostName' => $trip->host->name,
                'when' => $departure->starts_at->format('D, j M Y'),
                'seats' => $departure->spotsLeft(),
                'url' => route('trips.join', $trip),
            ]);
    }
}
