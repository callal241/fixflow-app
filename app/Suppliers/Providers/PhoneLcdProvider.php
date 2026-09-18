<?php

namespace App\Suppliers\Providers;

/**
 * PhoneLCD B2B parts supplier.
 *
 * Like MobileSentrix, PhoneLCD is a partnership (B2B) supplier with no public
 * parts API. Note: as of 2026-08-17 the public site (www.phonelcd.com) was
 * unreachable (SSL certificate mismatch / no resolvable API host), so there is
 * no known public endpoint to default to -- a working base_url + key must
 * come from the partnership.
 *
 * Configured by credentials: a base_url + api_key. When
 * unset, isConfigured() is false and searches return a clear failure.
 */
final class PhoneLcdProvider extends HttpSupplierProvider
{
    public function id(): string
    {
        return 'phonelcd';
    }

    public function name(): string
    {
        return 'PhoneLCD';
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== null && $this->apiKey() !== null;
    }

    public function configurationHint(): ?string
    {
        return $this->isConfigured()
            ? null
            : 'PhoneLCD is a B2B partner. Set PHONELCD_BASE_URL and PHONELCD_API_KEY (from your partnership agreement) to enable it.';
    }

    /**
     * @param  array<string, mixed>  $raw
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
