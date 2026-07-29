<?php

namespace App\Exceptions;

use RuntimeException;

class TripFullException extends RuntimeException
{
    public function __construct(string $message = 'This departure is full.')
    {
        parent::__construct($message);
    }
}
