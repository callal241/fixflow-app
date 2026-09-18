<?php

namespace App\Suppliers;

use App\Exceptions\UnknownSupplierException;
use App\Models\Business;
use ReflectionClass;

/**
 * Resolves the concrete {@see SupplierProvider} to use.
 *
 * Resolution order:
 *   1. The business's own choice (business->supplier_provider_id), when the
 *      provider is registered and configured for that business.
 *   2. The configured default (config('suppliers.default')).
 *   3. The built-in manual provider, as a guaranteed fallback so the supplier
 *      settings/search UI always works even if configuration is broken.
 *
 * Registered as a singleton in AppServiceProvider.
 */
final class SupplierProviderRegistry
{
    /**
     * The supplier a given business will use for its next parts lookup.
     */
    public function for(?Business $business): SupplierProvider
    {
        if ($business?->supplier_provider_id !== null) {
            $provider = $this->resolve($business->supplier_provider_id);
            if ($provider !== null && $provider->isConfigured()) {
                return $provider;
            }
        }

        $default = $this->resolve(config('suppliers.default', 'manual'));

        return $default ?? $this->resolve('manual') ?? $this->make('manual');
    }

    /**
     * The manual provider, always available.
     */
    public function manual(): SupplierProvider
    {
        return $this->make('manual');
    }

    /**
     * All registered providers, for settings UIs.
     *
     * @return array<string, SupplierProvider>
     */
    public function all(): array
    {
        $providers = config('suppliers.providers', []);

        $result = [];
        foreach (array_keys($providers) as $id) {
            $instance = $this->resolve((string) $id);
            if ($instance !== null) {
                $result[(string) $id] = $instance;
            }
        }

        return $result;
    }

    /**
     * Metadata (label/description/configured/hint) for every registered
     * provider id.
     *
     * @return array<string, array{label: string, description: string, configured: bool, hint: ?string}>
     */
    public function options(): array
    {
        $options = [];
        foreach (config('suppliers.providers', []) as $id => $meta) {
            $instance = $this->resolve((string) $id);
            $options[(string) $id] = [
                'label' => $meta['label'] ?? (string) $id,
                'description' => $meta['description'] ?? '',
                'configured' => $instance?->isConfigured() ?? false,
                'hint' => $instance?->configurationHint(),
            ];
        }

        return $options;
    }

    public function resolve(?string $id): ?SupplierProvider
    {
        if ($id === null || $id === '') {
            return null;
        }

        // Unknown ids resolve to null so callers can fall back gracefully
        // (e.g. a shop whose stored provider id no longer exists). A *known*
        // id whose class is invalid is a real misconfiguration and still
        // throws via make().
        if (! array_key_exists($id, config('suppliers.providers', []))) {
            return null;
        }

        return $this->make($id);
    }

    private function make(string $id): SupplierProvider
    {
        $config = config("suppliers.providers.{$id}");

        if (! is_array($config) || blank($config['class'] ?? null)) {
            throw UnknownSupplierException::for($id);
        }

        $class = $config['class'];

        if (! is_a($class, SupplierProvider::class, true)) {
            throw UnknownSupplierException::for($id);
        }

        $instance = new ReflectionClass($class);

        return $instance->newInstance();
    }
}
