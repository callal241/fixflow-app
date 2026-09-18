<?php

namespace App\Suppliers\Providers;

use App\Suppliers\PartOffer;

/**
 * iFixit parts & repair-guide catalog.
 *
 * This is a real integration against the public iFixit API
 * (https://www.ifixit.com/api/2.0). No API key is required, so it is the
 * default supplier and works out-of-the-box.
 *
 * The public search endpoint is /suggest/{query} (a path parameter). It
 * returns matches across guides and parts. The public API does NOT expose
 * catalog price/stock, so offers come back with price/stock = null -- we do
 * not fabricate pricing. The offer carries the part/guide name, a link, an
 * image, and the device category, which is enough to start a repair order or
 * source the part.
 */
final class IFixitProvider extends HttpSupplierProvider
{
    public function id(): string
    {
        return 'ifixit';
    }

    public function name(): string
    {
        return 'iFixit';
    }

    public function isConfigured(): bool
    {
        // Public API: only needs a base URL, which always has a default.
        return $this->baseUrl() !== null;
    }

    public function configurationHint(): ?string
    {
        return $this->isConfigured()
            ? null
            : 'iFixit needs a base_url. Set IFIXIT_BASE_URL (defaults to https://www.ifixit.com/api/2.0).';
    }

    /**
     * Normalize an iFixit /suggest payload.
     *
     * Shape: { "query": "...", "results": [ { dataType, title, url, category,
     * subject, image: { standard, ... }, ... }, ... ] }.
     *
     * @param  array<string, mixed>  $raw
     * @return PartOffer[]
     */
    protected function parseResponse(array $raw): array
    {
        $offers = [];
        $seen = [];

        foreach ($this->extractItems($raw) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $dataType = strtolower((string) ($row['dataType'] ?? $row['type'] ?? ''));

            $name = $this->pick($row, ['title', 'name', 'part_name', 'subject']);
            if ($name === null || $name === '') {
                continue;
            }

            // De-duplicate by url, then by name, so repeated suggestions don't
            // clutter the list.
            $url = $this->pick($row, ['url', 'link', 'permalink']);
            $dedupe = $url ?? $name;
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;

            $image = null;
            if (isset($row['image']) && is_array($row['image'])) {
                $image = $this->pick($row['image'], ['standard', 'thumbnail', 'medium', 'original', 'url'])
                    ?? $this->pick($row['image'], array_keys($row['image']));
            }

            $offers[] = new PartOffer(
                supplier: $this->id(),
                name: $name,
                externalId: $this->pick($row, ['guideid', 'partid', 'id']),
                url: $url,
                image: $image,
                price: null, // public iFixit API has no catalog pricing
                stock: null, // public iFixit API has no stock data
                category: $this->pick($row, ['category', 'brand']) ?? ($dataType !== '' ? ucfirst($dataType) : null),
                description: $this->pick($row, ['summary']),
                extra: ['data_type' => $dataType !== '' ? $dataType : null],
            );
        }

        return $offers;
    }
}
