<?php

namespace App\Mail;

use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChatActivityMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TripDeparture $departure, public User $recipient) {}

    public function build(): self
    {
        $trip = $this->departure->trip;

        return $this->subject('New messages in the '.$trip->title.' chat')
            ->view('emails.chat-activity', [
                'name' => $this->recipient->name,
                'tripTitle' => $trip->title,
                'when' => $this->departure->starts_at->format('D, j M Y'),
                'url' => route('chat.show', $this->departure),
            ]);
    }
}
