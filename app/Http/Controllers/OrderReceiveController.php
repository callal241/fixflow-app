<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Receive a part line on a ticket.
 *
 * Separate controller (whitelisted `index` method) because the arch() preset
 * only allows resource-style controller method names -- same constraint that
 * shaped SupplierSearchController.
 */
class OrderReceiveController extends Controller
{
    /**
     * Mark a part line received and settle its stock: own-stock lines consume
     * their reservation, purchase lines restock their linked catalog product.
     */
    public function index(Request $request, Ticket $ticket, Order $order): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);
        abort_unless($order->ticket_id === $ticket->id, 404);

        if ($order->status === OrderStatus::Cancelled) {
            return to_route('tickets.show', ['ticket' => $ticket])
                ->withErrors(['order' => 'A cancelled part cannot be received.']);
        }

        $order->forceFill([
            'status' => OrderStatus::Received,
            'received_at' => now(),
        ])->save();

        return to_route('tickets.show', ['ticket' => $ticket])
            ->with('success', "Received \"{$order->name}\" x {$order->quantity}.");
    }
}
