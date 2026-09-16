<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Models\Adjustment;
use App\Models\Invoice;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdjustmentController extends Controller
{
    /**
     * Add an adjustment (discount, fee, compensation, bonus) to the ticket's invoice.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(AdjustmentType::values())],
            'reason' => ['required', 'string', Rule::in(AdjustmentReason::values())],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $invoice = $ticket->invoice;
        if ($invoice === null) {
            return to_route('tickets.show', $ticket)
                ->withErrors(['type' => 'Generate the invoice first.']);
        }

        $hasAmount = ! empty($validated['amount']);
        $hasPct = ! empty($validated['percentage']);
        if (! $hasAmount && ! $hasPct) {
            return to_route('tickets.show', $ticket)
                ->withErrors(['amount' => 'Provide an amount or a percentage.']);
        }

        $invoice->adjustments()->create([
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'amount' => $hasAmount ? round((float) $validated['amount'], 2) : null,
            'percentage' => $hasPct ? (float) $validated['percentage'] : null,
            'note' => $validated['note'] ?? null,
        ]);

        $this->resync($invoice);

        return to_route('tickets.show', $ticket)
            ->with('success', 'Adjustment added to the invoice.');
    }

    /**
     * Remove an adjustment from the invoice.
     */
    public function destroy(Request $request, Ticket $ticket, Adjustment $adjustment): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);
        abort_unless($adjustment->invoice?->ticket_id === $ticket->id, 404);

        $adjustment->delete();

        $this->resync($ticket->invoice);

        return to_route('tickets.show', $ticket)
            ->with('success', 'Adjustment removed.');
    }

    /**
     * Recompute totals, adjustments, status, and persist the invoice.
     */
    private function resync(Invoice $invoice): void
    {
        $invoice->load('adjustments');

        $invoice
            ->fillTaskTotal()
            ->fillOrderTotal()
            ->fillAdjustmentAmounts()
            ->syncTotal()
            ->fillStatus()
            ->save();
    }
}
