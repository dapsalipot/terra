<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidTransitionException extends RuntimeException
{
    public static function from(string $status, string $action): self
    {
        return new self("Cannot {$action} a reservation in status [{$status}].");
    }
}
