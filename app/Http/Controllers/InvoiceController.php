<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /**
     * Generate (or refresh) the invoice for a ticket from its billable tasks + orders.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);

        $validated = $request->validate([
            'due_date' => ['nullable', 'date'],
        ]);

        $invoice = $ticket->invoice()->first();

        if ($invoice === null) {
            $invoice = new Invoice;
            $invoice->forceFill(['ticket_id' => $ticket->id]);
            $invoice->business_id = $ticket->business_id;
        }

        $invoice
            ->fillTaskTotal()
            ->fillOrderTotal()
            ->fillAdjustmentAmounts()
            ->syncTotal()
            ->fillStatus();
        $invoice->due_date = $validated['due_date'] ?? $invoice->due_date;
        if ($invoice->due_date === null) {
            $invoice->due_date = now()->addWeeks(2);
        }
        $invoice->save();

        return to_route('tickets.show', $ticket)
            ->with('success', 'Invoice generated from billable work.');
    }
}
