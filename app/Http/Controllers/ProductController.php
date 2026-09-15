<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * List the products for the acting user's business.
     */
    public function index(Request $request): Response
    {
        $businessId = $request->user()->business_id;

        $products = Product::forBusiness($businessId)
            ->with('category:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Products/Index', [
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'condition' => $p->condition,
                'price' => (float) $p->price,
                'stock' => $p->stock,
                'reorder_level' => $p->reorder_level,
                'is_active' => $p->is_active,
                'is_low_stock' => $p->isLowStock(),
                'category' => $p->category?->name,
            ]),

            'categories' => Category::forBusiness($businessId)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),

            'low_stock_count' => Product::forBusiness($businessId)->active()->lowStock()->count(),
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(Request $request): Response
    {
        $businessId = $request->user()->business_id;

        return Inertia::render('Products/Create', [
            'categories' => Category::forBusiness($businessId)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Store a new product for the acting user's business.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('business_id', $request->user()->business_id),
            ],
            'sku' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        // The business is stamped automatically by the BelongsToBusiness trait
        // (from the acting user), so we never trust a business_id from the form.
        $product = Product::create([
            'category_id' => $validated['category_id'] ?? null,
            'name' => $validated['name'],
            'sku' => $validated['sku'] ?? null,
            'barcode' => $validated['barcode'] ?? null,
            'condition' => $validated['condition'] ?? null,
            'price' => $validated['price'],
            'cost' => $validated['cost'] ?? 0,
            'stock' => $validated['stock'],
            'reorder_level' => $validated['reorder_level'] ?? 0,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return to_route('products.index')->with('success', "Product \"{$product->name}\" added.");
    }
}
