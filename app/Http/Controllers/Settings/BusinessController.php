<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Payments\PaymentProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
    ) {
    }

    /**
     * Business-level settings: how the shop takes payments.
     */
    public function edit(Request $request): Response
    {
        $business = $request->user()->business;

        return Inertia::render('settings/Business', [
            'business' => [
                'name' => $business?->name ?? 'Business',
                'currency' => $business?->currency ?? 'USD',
                'payment_provider_id' => $business?->payment_provider_id,
            ],
            'providers' => $this->providers->options(),
            'default_provider_id' => config('payments.default', 'counter'),
            'active_provider_id' => $this->providers->for($business)->id(),
        ]);
    }

    /**
     * Persist the shop's payment provider choice.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payment_provider_id' => ['nullable', 'string', 'max:64'],
        ]);

        $business = $request->user()->business;

        if ($business === null) {
            return to_route('business.edit')->with('error', 'No business on this account.');
        }

        $id = $data['payment_provider_id'] ?? null;

        // Only allow ids that are actually registered (and configured).
        if ($id !== null) {
            $provider = $this->providers->resolve($id);
            $ok = $provider !== null && $provider->isConfigured();
            $data['payment_provider_id'] = $ok ? $id : null;
        }

        $business->update($data);

        return to_route('business.edit')->with('success', 'Payment provider updated.');
    }
}
