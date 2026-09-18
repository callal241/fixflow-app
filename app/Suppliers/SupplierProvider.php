<?php

namespace App\Suppliers;

/**
 * A parts supplier searches upstream catalogs and reports normalized results.
 *
 * Providers are intentionally vendor-agnostic. The application only ever talks
 * to this contract, so plugging in a new parts supplier means adding a new
 * implementation plus one entry in config('suppliers.providers') -- no changes
 * to controllers, the settings UI, or the order/part flow.
 *
 * Implementations must be stateless. They return a {@see PartSearchResult} and
 * never throw for a routine upstream problem (no results, timeout, non-2xx,
 * unparseable JSON, or "not configured yet"): the result carries the failure so
 * the UI degrades gracefully. A supplier that is not configured reports
 * isConfigured() = false and a clear searchParts() failure, so a shop can see
 * exactly what is missing before the integration goes live.
 */
interface SupplierProvider
{
    /**
     * Stable identifier, as used in config and on the business model.
     */
    public function id(): string;

    /**
     * Human readable name for settings and the parts UI.
     */
    public function name(): string;

    /**
     * Whether the supplier is ready to be used (e.g. partnership credentials
     * present, or a public API that needs none).
     */
    public function isConfigured(): bool;

    /**
     * Human readable explanation of what is needed when isConfigured() is
     * false (surfaced in settings), or null when configured.
     */
    public function configurationHint(): ?string;

    /**
     * Search the supplier's catalog for parts matching {@see $query}.
     *
     * Returns a {@see PartSearchResult}; never throws for a soft failure.
     *
     * @param  array<string, mixed>  $context  Provider-specific hints (business id, device, etc.).
     */
    public function searchParts(string $query, int $limit = 10, array $context = []): PartSearchResult;
}
