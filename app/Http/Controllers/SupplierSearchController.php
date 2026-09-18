<?php

namespace App\Http\Controllers;

use App\Suppliers\SupplierProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON parts-search endpoint, consumed by the Suppliers page (and later by the
 * per-ticket "find parts" flow).
 *
 * Mirrors SearchController: a single `index` action returns JSON. It is a
 * separate controller so SupplierController keeps only whitelisted resource
 * method names (index/update), which the arch() preset enforces.
 */
class SupplierSearchController extends Controller
{
    public function __construct(
        private readonly SupplierProviderRegistry $suppliers,
    ) {
    }

    /**
     * Search parts via the acting business's active supplier.
     *
     * The provider is resolved per-request from the business so a tenant
     * searching parts always uses its own configured supplier. A supplier that
     * is not configured or unreachable returns HTTP 200 with success=false and
     * a reason -- the UI shows that instead of failing.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $business = $request->user()->business;
        $provider = $this->suppliers->for($business);

        $result = $provider->searchParts(
            trim((string) $request->query('q')),
            (int) $request->query('limit', 10),
        );

        return response()->json($result->toPayload());
    }
}
