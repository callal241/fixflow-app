<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when the requested payment provider id is not registered in
 * config('payments.providers').
 */
final class UnknownProviderException extends RuntimeException
{
    public static function for(string $id): self
    {
        return new self("Unknown payment provider [{$id}]. Register it in config('payments.providers').");
    }
}
