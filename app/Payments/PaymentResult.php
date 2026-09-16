<?php

namespace App\Payments;

use App\Enums\TransactionMethod;

/**
 * Immutable result of a payment attempt from a provider.
 *
 * On success the provider returns the authoritative details (including any
 * external transaction id) so the application can record a faithful
 * transaction. On failure it returns the provider's reason and, for soft
 * declines, the provider's response code.
 */
final class PaymentResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $reference,
        public readonly ?string $failureReason,
        public readonly ?string $responseCode,
        public readonly TransactionMethod $method,
    ) {
    }

    public static function success(TransactionMethod $method, ?string $reference = null, ?string $responseCode = null): self
    {
        return new self(true, $reference, null, $responseCode, $method);
    }

    public static function failure(TransactionMethod $method, string $reason, ?string $responseCode = null): self
    {
        return new self(false, null, $reason, $responseCode, $method);
    }
}
