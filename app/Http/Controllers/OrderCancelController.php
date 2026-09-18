<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cancel a part line on a ticket.
 *
 * Separate controller (whitelisted `index` method) because the arch() preset
 * only allows resource-style controller method names -- same constraint that
 * shaped SupplierSearchController.
 */
class OrderCancelController extends Controller
{
    /**
     * Cancel a part line: it becomes non-billable (spec: cancelled orders are
     * non-billable) and any reserved stock is released (OrderObserver).
     */
    public function index(Request $request, Ticket $ticket, Order $order): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);
        abort_unless($order->ticket_id === $ticket->id, 404);

        if ($order->status === OrderStatus::Received) {
            return to_route('tickets.show', ['ticket' => $ticket])
                ->withErrors(['order' => 'A received part cannot be cancelled.']);
        }

        $order->forceFill([
            'status' => OrderStatus::Cancelled,
            'is_billable' => false,
        ])->save();

        return to_route('tickets.show', ['ticket' => $ticket])
            ->with('success', "Cancelled \"{$order->name}\".");
    }
}
