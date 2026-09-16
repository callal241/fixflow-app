<?php

namespace App\Payments;

use App\Enums\TransactionMethod;

/**
 * A payment provider captures money for a business and reports the result.
 *
 * Providers are intentionally vendor-agnostic. The application only ever talks
 * to this contract, so plugging in a new reader or gateway means adding a new
 * implementation plus one entry in config('payments.providers') — no changes
 * to controllers, the checkout UI, or the transaction ledger.
 *
 * Implementations must be stateless and side-effect free on the ledger: they
 * return a {@see PaymentResult} and the application is responsible for
 * recording the transaction, so a failed/abandoned capture never leaves a
 * partial record.
 */
interface PaymentProvider
{
    /**
     * Stable identifier, as used in config and on the business model.
     */
    public function id(): string;

    /**
     * Human readable name for settings and receipts.
     */
    public function name(): string;

    /**
     * Whether this provider can handle the given method (e.g. a cash-only
     * terminal cannot take online payments).
     */
    public function supports(TransactionMethod $method): bool;

    /**
     * Whether the provider is ready to be used (e.g. gateway credentials
     * present). Providers that need no configuration always return true.
     */
    public function isConfigured(): bool;

    /**
     * Authorize and capture {@see $amount} for the business using the given
     * method. Returns a {@see PaymentResult}; never throws for a soft decline.
     *
     * @param  array<string, mixed>  $context  Provider-specific hints (invoice id, customer, etc.).
     */
    public function charge(float $amount, TransactionMethod $method, ?string $currency = null, array $context = []): PaymentResult;
}
