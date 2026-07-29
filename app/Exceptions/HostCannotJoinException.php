<?php

namespace App\Exceptions;

use RuntimeException;

class HostCannotJoinException extends RuntimeException
{
    public function __construct(string $message = 'Host accounts cannot join trips. Sign up as a joiner to reserve.')
    {
        parent::__construct($message);
    }
}
