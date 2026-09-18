<?php

namespace App\Suppliers\Providers;

use App\Suppliers\PartOffer;
use App\Suppliers\PartSearchResult;
use App\Suppliers\SupplierProvider;

/**
 * Base class for suppliers that expose an HTTP catalog/search endpoint.
 *
 * Subclasses provide the identity (id/name/hint), what counts as "configured"
 * (iFixit: a base URL, always present; B2B partners: base URL + API key), and
 * how to normalize the vendor's JSON into {@see PartOffer}s. Everything else --
 * building the request URL from config, applying the optional auth header, the
 * timeout, and converting any upstream problem into a failure result rather
 * than an exception -- lives here.
 *
 * The network layer is intentionally thin and side-effect free. `parseResponse`
 * is a pure function of the decoded JSON so the mapping is unit-testable without
 * touching the network (tests feed a recorded fixture).
 */
abstract class HttpSupplierProvider implements SupplierProvider
{
    /** Seconds to wait on the upstream supplier. */
    protected int $timeout = 15;

    /**
     * Per-vendor settings from config('suppliers.gateways.{id}').
     *
     * @return array<string, mixed>
     */
    protected function gateway(): array
    {
        return config("suppliers.gateways.{$this->id()}", []) ?: [];
    }

    protected function baseUrl(): ?string
    {
        $base = $this->gateway()['base_url'] ?? null;

        return is_string($base) && $base !== '' ? rtrim($base, '/') : null;
    }

    protected function apiKey(): ?string
    {
        $key = $this->gateway()['api_key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    protected function authHeader(): string
    {
        return (string) ($this->gateway()['auth_header'] ?? 'Authorization');
    }

    /**
     * Build the absolute search URL from the configured base + path template.
     */
    protected function searchUrl(string $query, int $limit): ?string
    {
        $base = $this->baseUrl();

        if ($base === null) {
            return null;
        }

        $path = (string) ($this->gateway()['search_path'] ?? '/search?q={query}&limit={limit}');
        $path = str_replace(['{query}', '{limit}'], [rawurlencode($query), (string) $limit], $path);

        return $base . $path;
    }

    /**
     * Perform the search. Returns a failure result (never throws) for any
     * upstream problem: not configured, no URL, transport error, non-2xx, or
     * an unparseable / unexpected body.
     *
     * @param  array<string, mixed>  $context
     */
    public function searchParts(string $query, int $limit = 10, array $context = []): PartSearchResult
    {
        if (! $this->isConfigured()) {
            return PartSearchResult::failure($this->id(), (string) $this->configurationHint());
        }

        $query = trim($query);
        if ($query === '') {
            return PartSearchResult::failure($this->id(), 'Search query is empty.');
        }

        $url = $this->searchUrl($query, $limit);
        if ($url === null) {
            return PartSearchResult::failure($this->id(), 'No supplier base_url is configured.');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $url,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'FixFlow/1.0 (+repair-shop parts lookup)',
            CURLOPT_HTTPHEADER => $this->headers(),
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return PartSearchResult::failure($this->id(), "Supplier request failed: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            return PartSearchResult::failure($this->id(), "Supplier responded with HTTP {$status}.", $status);
        }

        $decoded = json_decode((string) $body, true);
        if (! is_array($decoded)) {
            return PartSearchResult::failure($this->id(), 'Supplier returned a non-JSON response.', $status);
        }

        return PartSearchResult::success($this->id(), $this->parseResponse($decoded), $status, $decoded);
    }

    /**
     * @return array<int, string>
     */
    protected function headers(): array
    {
        $headers = ['Accept: application/json'];

        $key = $this->apiKey();
        if ($key !== null) {
            $headers[] = $this->authHeader() . ': ' . $key;
        }

        return $headers;
    }

    /**
     * Normalize the decoded supplier JSON into offers. Pure and network-free so
     * tests can call it with a recorded fixture.
     *
     * @param  array<string, mixed>  $raw
     * @return PartOffer[]
     */
    abstract protected function parseResponse(array $raw): array;

    // ---- shared best-effort mapping helpers for generic catalogs ----

    /**
     * Extract the array of result objects from a variety of envelope shapes
     * ({@code {results:[]}}, {@code {data:[]}}, {@code {parts:[]}}, or a bare
     * array). Returns an empty list when none match.
     *
     * @param  array<string, mixed>  $raw
     * @return array<int, array<string, mixed>>
     */
    protected function extractItems(array $raw): array
    {
        foreach (['results', 'data', 'parts', 'items', 'offers'] as $key) {
            if (isset($raw[$key]) && is_array($raw[$key]) && ! $this->isAssoc($raw[$key])) {
                return $raw[$key];
            }
        }

        return $this->isAssoc($raw) ? [] : $raw;
    }

    /**
     * @param  array<mixed>  $arr
     */
    protected function isAssoc(array $arr): bool
    {
        return $arr !== [] && array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Pick the first non-empty value from the given keys of a result row.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    protected function pick(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && is_scalar($row[$key]) && trim((string) $row[$key]) !== '') {
                return (string) $row[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function pickFloat(array $row, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && is_numeric($row[$key]) && (float) $row[$key] > 0) {
                return (float) $row[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function pickInt(array $row, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                return (int) $row[$key];
            }
        }

        return null;
    }

    /**
     * Build a PartOffer from a generic catalog row using common field names.
     *
     * @param  array<string, mixed>  $row
     */
    protected function offerFromRow(array $row): ?PartOffer
    {
        $name = $this->pick($row, ['name', 'title', 'part_name', 'product', 'description']);
        if ($name === null) {
            return null;
        }

        $image = null;
        if (isset($row['image']) && is_array($row['image'])) {
            $image = $this->pick($row['image'], ['standard', 'thumbnail', 'original', 'url']) ?? $this->pick($row['image'], array_keys($row['image']));
        } else {
            $image = $this->pick($row, ['image', 'image_url', 'thumbnail', 'photo']);
        }

        return new PartOffer(
            supplier: $this->id(),
            name: $name,
            externalId: $this->pick($row, ['id', 'sku', 'part_number', 'partid', 'guid']),
            url: $this->pick($row, ['url', 'link', 'href', 'permalink']),
            image: $image,
            price: $this->pickFloat($row, ['price', 'unit_price', 'cost', 'price_usd']),
            stock: $this->pickInt($row, ['stock', 'in_stock', 'available', 'quantity_available']),
            category: $this->pick($row, ['category', 'brand', 'make']),
            description: $this->pick($row, ['summary', 'detail', 'long_description']),
        );
    }
}
