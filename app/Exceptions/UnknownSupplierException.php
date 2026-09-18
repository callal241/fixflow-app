<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when the requested supplier provider id is not registered in
 * config('suppliers.providers').
 */
final class UnknownSupplierException extends RuntimeException
{
    public static function for(string $id): self
    {
        return new self("Unknown supplier provider [{$id}]. Register it in config('suppliers.providers').");
    }
}
