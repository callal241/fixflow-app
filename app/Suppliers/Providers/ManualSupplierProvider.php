<?php

namespace App\Suppliers\Providers;

use App\Suppliers\PartSearchResult;
use App\Suppliers\SupplierProvider;

/**
 * The no-integration supplier: parts are found and ordered by hand (phone,
 * portal, walk-in).
 *
 * It performs no software hand-off -- there is nothing to look up -- and it
 * always reports as configured so the supplier settings page and the "search
 * parts" flow never hard-fail. A search returns a friendly "no supplier"
 * result the UI turns into a hint rather than an error. It mirrors the payment
 * side's counter provider: a guaranteed, zero-config floor.
 */
final class ManualSupplierProvider implements SupplierProvider
{
    public function id(): string
    {
        return 'manual';
    }

    public function name(): string
    {
        return 'Manual lookup';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function configurationHint(): ?string
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function searchParts(string $query, int $limit = 10, array $context = []): PartSearchResult
    {
        return PartSearchResult::failure(
            $this->id(),
            'No supplier integration is active. Add or order the part manually, or pick iFixit (or a configured partner) in Settings -> Suppliers to search automatically.',
        );
    }
}
