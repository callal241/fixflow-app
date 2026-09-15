<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * List the tickets for the acting user's business.
     */
    public function index(Request $request): Response
    {
        $businessId = $request->user()->business_id;

        $tickets = Ticket::forBusiness($businessId)
            ->with(['device.customer:id,name', 'assignee:id,name'])
            ->latest()
            ->get();

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'device' => $ticket->device?->model,
                'customer' => $ticket->device?->customer?->name,
                'assignee' => $ticket->assignee?->name,
                'created_at' => $ticket->created_at?->toDateTimeString(),
            ]),
        ]);
    }

    /**
     * Show a single ticket with its order lines and the catalog to sell from.
     */
    public function show(Request $request, Ticket $ticket): Response
    {
        // Enforce business scoping: the ticket must belong to the acting user's business.
        abort_unless($ticket->business_id === $request->user()->business_id, 403);

        $ticket = $ticket->load(['device.customer:id,name', 'assignee:id,name']);

        $orders = $ticket->orders()
            ->with('product:id,name,sku,price,stock,condition')
            ->latest()
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'name' => $order->name,
                'quantity' => $order->quantity,
                'price' => (float) $order->price,
                'cost' => (float) $order->cost,
                'is_billable' => $order->is_billable,
                'status' => $order->status->value,
                'product' => $order->product ? [
                    'id' => $order->product->id,
                    'name' => $order->product->name,
                    'sku' => $order->product->sku,
                    'price' => (float) $order->product->price,
                    'stock' => $order->product->stock,
                    'condition' => $order->product->condition,
                ] : null,
            ]);

        // Catalog the shop can sell from on this ticket (their business, active only).
        $products = Product::forBusiness($request->user()->business_id)
            ->active()
            ->with('category:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => (float) $p->price,
                'stock' => $p->stock,
                'condition' => $p->condition,
                'category' => $p->category?->name,
            ]);

        // The billable totals the invoice would show.
        $orderTotal = (float) $ticket->orders()->billable()->sum('cost');
        $taskTotal = (float) $ticket->tasks()->billable()->sum('cost');

        return Inertia::render('Tickets/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'title' => $ticket->title,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'device' => $ticket->device?->model,
                'brand' => $ticket->device?->brand,
                'customer' => $ticket->device?->customer?->name,
                'assignee' => $ticket->assignee?->name,
                'created_at' => $ticket->created_at?->toDateTimeString(),
            ],
            'orders' => $orders,
            'products' => $products,
            'totals' => [
                'task_total' => $taskTotal,
                'order_total' => $orderTotal,
                'subtotal' => $taskTotal + $orderTotal,
            ],
        ]);
    }
}
