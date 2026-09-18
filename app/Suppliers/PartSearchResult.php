<?php

namespace App\Suppliers;

/**
 * Immutable result of a parts search from a supplier.
 *
 * On success it carries the normalized {@see PartOffer}s (and the provider's
 * raw payload, for transparency / debugging). On failure it carries a stable
 * reason and an optional upstream status code, so the UI can show an honest
 * "supplier unavailable" state instead of a 500.
 *
 * Providers must never throw for a routine upstream error (timeout, non-2xx,
 * unparseable body); they return a failure result instead. The only things
 * that throw are genuine misconfiguration (an unknown provider id).
 */
final class PartSearchResult
{
    /**
     * @param  PartOffer[]  $offers
     * @param  array<string, mixed>|null  $raw
     */
    private function __construct(
        public readonly string $supplier,
        public readonly bool $success,
        public readonly array $offers,
        public readonly ?string $failureReason,
        public readonly ?int $statusCode,
        public readonly ?array $raw,
    ) {
    }

    /**
     * @param  PartOffer[]  $offers
     * @param  array<string, mixed>|null  $raw
     */
    public static function success(string $supplier, array $offers, ?int $statusCode = null, ?array $raw = null): self
    {
        return new self($supplier, true, $offers, null, $statusCode, $raw);
    }

    public static function failure(string $supplier, string $reason, ?int $statusCode = null): self
    {
        return new self($supplier, false, [], $reason, $statusCode, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'supplier' => $this->supplier,
            'success' => $this->success,
            'offers' => array_map(fn (PartOffer $o) => $o->toPayload(), $this->offers),
            'count' => count($this->offers),
            'failure_reason' => $this->failureReason,
            'status_code' => $this->statusCode,
        ];
    }
}
