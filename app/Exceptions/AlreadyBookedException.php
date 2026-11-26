<?php

namespace App\Exceptions;

use App\Models\Reservation;
use RuntimeException;

/** A signed-in joiner already has a live booking on this departure. Carries it, so they can be sent there. */
class AlreadyBookedException extends RuntimeException
{
    public function __construct(public readonly Reservation $reservation)
    {
        parent::__construct('You already have a booking on this departure.');
    }
}
