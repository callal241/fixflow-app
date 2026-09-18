<?php

namespace App\Http\Controllers;

use App\Suppliers\SupplierProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierProviderRegistry $suppliers,
    ) {
    }

    /**
     * Persist the shop's parts-supplier choice.
     *
     * Only ids that are actually registered (and configured) are accepted;
     * anything else is normalised back to null (meaning "use the default"),
     * mirroring how the payment provider choice is handled.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_provider_id' => ['nullable', 'string', 'max:64'],
        ]);

        $business = $request->user()->business;

        if ($business === null) {
            return to_route('suppliers.index')->with('error', 'No business on this account.');
        }

        $id = $data['supplier_provider_id'] ?? null;

        if ($id !== null && $id !== '') {
            $provider = $this->suppliers->resolve($id);
            $id = ($provider !== null && $provider->isConfigured()) ? $id : null;
        }

        // Always write the column explicitly so "no value" clears the choice.
        $business->update(['supplier_provider_id' => $id]);

        return to_route('suppliers.index')->with('success', 'Parts supplier updated.');
    }

    /**
     * Parts sourcing: pick the active supplier and search its catalog.
     *
     * The page renders the same data the settings flow would, but lives in the
     * main nav because finding parts is part of daily work, not a one-time
     * setup. The search box below the provider list hits suppliers.search and
     * renders normalized offers (name, category, price/stock when the supplier
     * provides them, a link) so a technician can see what a supplier has
     * before opening an order.
     */
    public function index(Request $request): Response
    {
        $business = $request->user()->business;
        $active = $this->suppliers->for($business);

        return Inertia::render('Suppliers/Index', [
            'business' => [
                'supplier_provider_id' => $business?->supplier_provider_id,
            ],
            'suppliers' => $this->suppliers->options(),
            'default_supplier_id' => config('suppliers.default', 'manual'),
            'active_supplier_id' => $active->id(),
        ]);
    }
}
