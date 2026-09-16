<?php

namespace App\Payments;

use App\Exceptions\UnknownProviderException;
use App\Models\Business;
use ReflectionClass;

/**
 * Resolves the concrete {@see PaymentProvider} to use.
 *
 * Resolution order:
 *   1. The business's own choice (business->payment_provider_id), when the
 *      provider is registered and configured for that business.
 *   2. The configured default (config('payments.default')).
 *   3. The built-in counter provider, as a guaranteed fallback so checkout
 *      always works even if configuration is broken.
 *
 * Registered as a singleton in AppServiceProvider.
 */
final class PaymentProviderRegistry
{
    /**
     * The provider a given business will use for its next payment.
     */
    public function for(?Business $business): PaymentProvider
    {
        if ($business?->payment_provider_id !== null) {
            $provider = $this->resolve($business->payment_provider_id);
            if ($provider !== null && $provider->isConfigured()) {
                return $provider;
            }
        }

        $default = $this->resolve(config('payments.default', 'counter'));

        return $default ?? $this->resolve('counter') ?? $this->make('counter');
    }

    /**
     * The counter provider, always available.
     */
    public function counter(): PaymentProvider
    {
        return $this->make('counter');
    }

    /**
     * All registered providers, for settings UIs.
     *
     * @return array<string, PaymentProvider>
     */
    public function all(): array
    {
        $providers = config('payments.providers', []);

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
     * Metadata (label/description) for every registered provider id.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public function options(): array
    {
        $options = [];
        foreach (config('payments.providers', []) as $id => $meta) {
            $options[(string) $id] = [
                'label' => $meta['label'] ?? (string) $id,
                'description' => $meta['description'] ?? '',
            ];
        }

        return $options;
    }

    public function resolve(?string $id): ?PaymentProvider
    {
        if ($id === null || $id === '') {
            return null;
        }

        // Unknown ids resolve to null so callers can fall back gracefully
        // (e.g. a shop whose stored provider id no longer exists). A *known*
        // id whose class is invalid is a real misconfiguration and still
        // throws via make().
        if (! array_key_exists($id, config('payments.providers', []))) {
            return null;
        }

        return $this->make($id);
    }

    private function make(string $id): PaymentProvider
    {
        $config = config("payments.providers.{$id}");

        if (! is_array($config) || blank($config['class'] ?? null)) {
            throw UnknownProviderException::for($id);
        }

        $class = $config['class'];

        if (! is_a($class, PaymentProvider::class, true)) {
            throw UnknownProviderException::for($id);
        }

        $instance = new ReflectionClass($class);

        return $instance->newInstance();
    }
}
