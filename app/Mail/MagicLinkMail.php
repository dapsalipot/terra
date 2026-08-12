<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $url, public string $name) {}

    public function build(): self
    {
        return $this->subject('Your Terra sign-in link')
            ->view('emails.magic-link');
    }
}
