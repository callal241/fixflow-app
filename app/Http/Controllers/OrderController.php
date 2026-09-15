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
     * Sell a catalog product against a ticket: creates a billable order line and
     * consumes stock (via the OrderObserver).
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);

        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('business_id', $request->user()->business_id)
                    ->where('is_active', true),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_billable' => ['boolean'],
        ]);

        $product = Product::forBusiness($request->user()->business_id)
            ->find($validated['product_id']);

        $quantity = (int) $validated['quantity'];

        if ($product->stock < $quantity) {
            return to_route('tickets.show', ['ticket' => $ticket])
                ->withErrors(['product_id' => "Only {$product->stock} in stock."])
                ->withInput();
        }

        // Create through the ticket's relation so ticket_id is set by the FK;
        // business_id is stamped by the BelongsToBusiness trait (the acting user's
        // business, which the 403 above guarantees equals the ticket's business).
        $ticket->orders()->create([
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => $quantity,
            'price' => $product->price,
            'cost' => round($product->price * $quantity, 2),
            'is_billable' => $validated['is_billable'] ?? true,
            'status' => OrderStatus::New,
        ]);

        return to_route('tickets.show', ['ticket' => $ticket])
            ->with('success', "Added \"{$product->name}\" x {$quantity} to the ticket.");
    }

    /**
     * Remove a product line from a ticket: deletes the order and releases stock
     * (via the OrderObserver).
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
