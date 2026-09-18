<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /**
     * Add a part line to a ticket. Two shapes:
     *
     *  - Catalog product pulled from the shop's own shelf (product_id set):
     *    price is snapshotted from the product, and the quantity is RESERVED
     *    against available stock (consumed when the part is received).
     *  - Free-form supplier part ("find parts", no product_id): name required;
     *    optionally a purchase of a catalog product (is_purchase) that restocks
     *    it when received.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);

        $validated = $request->validate([
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')
                    ->where('business_id', $request->user()->business_id)
                    ->where('is_active', true),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:2048'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'is_billable' => ['boolean'],
            'is_purchase' => ['boolean'],
        ]);

        $quantity = (int) $validated['quantity'];
        $asPurchase = (bool) ($validated['is_purchase'] ?? false);

        $product = isset($validated['product_id'])
            ? Product::forBusiness($request->user()->business_id)->find($validated['product_id'])
            : null;

        if ($product !== null) {
            // Price defaults to the catalog snapshot; an explicit price wins
            // (e.g. a supplier offer priced differently than the shelf).
            $price = array_key_exists('price', $validated) && $validated['price'] !== null
                ? round((float) $validated['price'], 2)
                : (float) $product->price;

            // Pulling from the shop's own shelf requires available stock;
            // a purchase line creates stock (received later), so it is exempt.
            if (! $asPurchase && ! $product->hasAvailable($quantity)) {
                return to_route('tickets.show', ['ticket' => $ticket])
                    ->withErrors([
                        'product_id' => "Only {$product->available} available ({$product->stock} on hand, {$product->reserved_stock} reserved).",
                    ])
                    ->withInput();
            }
        } else {
            if (trim((string) ($validated['name'] ?? '')) === '') {
                return to_route('tickets.show', ['ticket' => $ticket])
                    ->withErrors([
                        'name' => 'A part name is required when not picking a catalog product.',
                    ])
                    ->withInput();
            }
            $price = round((float) ($validated['price'] ?? 0), 2);
        }

        $partName = $product !== null ? $product->name : trim((string) $validated['name']);

        // Create through the ticket's relation so ticket_id is set by the FK;
        // business_id is stamped by the BelongsToBusiness trait (the acting
        // user's business, which the 403 above guarantees equals the ticket's).
        $ticket->orders()->create([
            'product_id' => $product?->id,
            'name' => $partName,
            'url' => $validated['url'] ?? null,
            'supplier' => $validated['supplier'] ?? null,
            'quantity' => $quantity,
            'price' => $price,
            'cost' => round($price * $quantity, 2),
            'is_billable' => $validated['is_billable'] ?? true,
            'is_purchase' => $asPurchase,
            'status' => OrderStatus::New,
        ]);

        return to_route('tickets.show', ['ticket' => $ticket])
            ->with('success', "Added \"{$partName}\" x {$quantity} to the ticket.");
    }

    /**
     * Remove a part line from a ticket: deletes the order and releases any
     * reserved stock (via the OrderObserver).
     */
    public function destroy(Request $request, Ticket $ticket, Order $order): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);
        abort_unless($order->ticket_id === $ticket->id, 404);

        $order->delete();

        return to_route('tickets.show', ['ticket' => $ticket])
            ->with('success', 'Product removed from the ticket.');
    }
}
