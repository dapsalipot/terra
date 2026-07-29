<?php

namespace App\Support;

enum AccountOutcome: string
{
    case Guest = 'guest';                // booked without an account, by choice
    case AccountCreated = 'created';     // passwordless account made and attached
    case AlreadySignedIn = 'signed_in';  // existing joiner, attached directly
    case EmailTaken = 'email_taken';     // guest booking + attach link issued
}
