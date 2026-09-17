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

    /**
     * Show the form for editing a product.
     */
    public function edit(Request $request, Product $product): Response
    {
        abort_unless($product->business_id === $request->user()->business_id, 403);

        return Inertia::render('Products/Edit', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'condition' => $product->condition,
                'price' => (float) $product->price,
                'cost' => (float) $product->cost,
                'stock' => (int) $product->stock,
                'reorder_level' => (int) $product->reorder_level,
                'description' => $product->description,
                'is_active' => $product->is_active,
                'category_id' => $product->category_id,
            ],
            'categories' => Category::forBusiness($request->user()->business_id)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Update an existing product for the acting business.
     *
     * An optional absolute "stock" value carries a receiving/restock or a
     * count correction (never below zero). Omit it to change details without
     * touching on-hand quantity.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->business_id === $request->user()->business_id, 403);

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
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $changes = $validated;
        if (array_key_exists('stock', $changes)) {
            $changes['stock'] = max(0, (int) $changes['stock']);
        }

        $product->update($changes);

        return to_route('products.index')->with('success', "Product \"{$product->name}\" updated.");
    }

    /**
     * Remove a product. Order line items keep their name/price snapshot
     * (orders.product_id is nullOnDelete), so nothing downstream breaks.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->business_id === $request->user()->business_id, 403);

        $name = $product->name;
        $product->delete();

        return to_route('products.index')->with('success', "Product \"{$name}\" removed.");
    }
}
