<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A connected provider's API said no, or could not be reached.
 * The message is for administrators; it is never shown to customers.
 */
class ProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $unreachable = false)
    {
        parent::__construct($message);
    }
}
