<?php

namespace App\Suppliers\Providers;

use App\Suppliers\PartOffer;

/**
 * MobileSentrix B2B parts supplier.
 *
 * MobileSentrix is a partnership (B2B) supplier: it has no public API and is
 * fronted by Cloudflare (a plain anonymous request gets a 403 "Just a moment"
 * challenge). Access is via a per-partner API base URL + key that the partner
 * provides during onboarding.
 *
 * This provider is therefore *configured by credentials*, exactly like a
 * payment gateway:
 *   - unconfigured (no base_url/api_key)  -> isConfigured() is false, and a
 *     search returns a clear failure so the UI shows what to set.
 *   - configured                          -> does a real GET against the
 *     partner's search endpoint (path from config) and maps the JSON onto
 *     PartOffers using common catalog field names (name/title, price, stock,
 *     sku, url, image).
 *
 * The mapping is intentionally tolerant because partnership response shapes
 * vary; any row without a usable name is dropped, never guessed.
 */
final class MobileSentrixProvider extends HttpSupplierProvider
{
    public function id(): string
    {
        return 'mobilesentrix';
    }

    public function name(): string
    {
        return 'MobileSentrix';
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== null && $this->apiKey() !== null;
    }

    public function configurationHint(): ?string
    {
        return $this->isConfigured()
            ? null
            : 'MobileSentrix is a B2B partner. Set MOBILESENTRIX_BASE_URL and MOBILESENTRIX_API_KEY (from your partnership agreement) to enable it.';
    }

    /**
     * Map a MobileSentrix search payload onto offers using common catalog
     * field names. Works against the generic shape; override per-partner if the
     * onboarding docs specify an exact schema.
     *
     * @param  array<string, mixed>  $raw
     * @return PartOffer[]
     */
    protected function parseResponse(array $raw): array
    {
        $offers = [];
        foreach ($this->extractItems($raw) as $row) {
            if (is_array($row)) {
                $offer = $this->offerFromRow($row);
                if ($offer !== null) {
                    $offers[] = $offer;
                }
            }
        }

        return $offers;
    }
}
