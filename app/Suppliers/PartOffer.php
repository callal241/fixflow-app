<?php

namespace App\Suppliers;

/**
 * A single part/guide offered by a supplier for a search query.
 *
 * The shape is deliberately small and vendor-neutral so every supplier
 * implementation maps its raw response onto the same fields, and the UI /
 * "add to order" flow never needs to know which supplier answered.
 *
 * Fields a supplier cannot provide (most notably price/stock for iFixit,
 * whose public API has no catalog pricing) are null -- never invented.
 */
final class PartOffer
{
    /**
     * @param  array<string, mixed>  $extra  Provider-specific passthrough (raw payload, etc.)
     */
    public function __construct(
        public readonly string $supplier,
        public readonly string $name,
        public readonly ?string $externalId = null,
        public readonly ?string $url = null,
        public readonly ?string $image = null,
        public readonly ?float $price = null,
        public readonly ?int $stock = null,
        public readonly ?string $category = null,
        public readonly ?string $description = null,
        public readonly array $extra = [],
    ) {
    }

    /**
     * Whether the offer carries a usable price (drives the UI "add to order").
     */
    public function hasPrice(): bool
    {
        return $this->price !== null && $this->price > 0;
    }

    /**
     * Stable display reference: the supplier's id, else the url, else the name.
     */
    public function reference(): string
    {
        return $this->externalId ?? parse_url($this->url ?? '', PHP_URL_PATH) ?? $this->name;
    }

    /**
     * Plain array for Inertia payloads / "add to order" prefill.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'supplier' => $this->supplier,
            'name' => $this->name,
            'external_id' => $this->externalId,
            'url' => $this->url,
            'image' => $this->image,
            'price' => $this->price,
            'stock' => $this->stock,
            'category' => $this->category,
            'description' => $this->description,
        ];
    }
}
