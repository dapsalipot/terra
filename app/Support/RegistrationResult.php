<?php

namespace App\Support;

use App\Models\Reservation;

final class RegistrationResult
{
    public function __construct(
        public Reservation $reservation,
        public AccountOutcome $outcome,
        /** Raw magic-link URL. Available only on the request that created it. */
        public ?string $magicUrl = null,
    ) {}
}
