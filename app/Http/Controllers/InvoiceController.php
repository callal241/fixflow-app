<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Ticket;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    /**
     * List this business's invoices, newest first, with their ticket + customer.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();

        $invoices = Invoice::forBusiness()
            ->with(['ticket:id,created_at,title,device_id', 'ticket.device:id,customer_id', 'ticket.device.customer:id,name,company'])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
                $query->where(function ($q) use ($like) {
                    $q->whereHas('ticket', fn ($t) => $t
                        ->where('title', 'like', $like)
                        ->orWhereHas('device', fn ($d) => $d
                            ->where('model', 'like', $like)
                            ->orWhereHas('customer', fn ($c) => $c
                                ->where('name', 'like', $like)
                                ->orWhere('company', 'like', $like))))
                        ->orWhereHas('device.customer', fn ($c) => $c
                            ->where('name', 'like', $like)
                            ->orWhere('company', 'like', $like));
                });
            })
            ->latest()
            ->get();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'ticket' => $invoice->ticket?->title,
                'customer' => $invoice->ticket?->device?->customer?->name,
                'company' => $invoice->ticket?->device?->customer?->company,
                'status' => $invoice->status->value,
                'total' => (float) $invoice->total,
                'paid_amount' => (float) $invoice->paid_amount,
                'due_date' => $invoice->due_date?->toDateString(),
                'created_at' => $invoice->created_at?->toDateString(),
            ]),
            'search' => $search,
        ]);
    }

    /**
     * Show one invoice: the customer, billable line items, adjustments,
     * payments, and the running totals.
     */
    public function show(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->business_id === $request->user()->business_id, 403);

        $invoice->load([
            'ticket:id,created_at,title,device_id',
            'ticket.device:id,customer_id',
            'ticket.device.customer:id,name,company,phone,email',
            'transactions:id,invoice_id,amount,type,method,note,created_at',
            'adjustments:id,invoice_id,type,amount,percentage,note',
        ]);

        return Inertia::render('Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'status' => $invoice->status->value,
                'business' => $invoice->business?->name,
                'total' => (float) $invoice->total,
                'task_total' => (float) $invoice->task_total,
                'order_total' => (float) $invoice->order_total,
                'discount_amount' => (float) $invoice->discount_amount,
                'fee_amount' => (float) $invoice->fee_amount,
                'compensation_amount' => (float) $invoice->compensation_amount,
                'bonus_amount' => (float) $invoice->bonus_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'refunded_amount' => (float) $invoice->refunded_amount,
                'net_amount' => (float) $invoice->net_amount,
                'balance' => (float) $invoice->balance,
                'due_date' => $invoice->due_date?->toDateString(),
                'approved_at' => $invoice->approved_at?->toDateString(),
                'created_at' => $invoice->created_at?->toDateString(),
                'customer' => [
                    'name' => $invoice->ticket->device?->customer?->name,
                    'company' => $invoice->ticket->device?->customer?->company,
                    'phone' => $invoice->ticket->device?->customer?->phone,
                    'email' => $invoice->ticket->device?->customer?->email,
                ],
                'ticket' => [
                    'id' => $invoice->ticket->id,
                    'title' => $invoice->ticket->title,
                ],
                'tasks' => $invoice->ticket->tasks()->billable()->get()->map(fn ($t) => [
                    'id' => $t->id,
                    'note' => $t->note,
                    'cost' => (float) $t->cost,
                ]),
                'orders' => $invoice->ticket->orders()->billable()->get()->map(fn ($o) => [
                    'id' => $o->id,
                    'name' => $o->name,
                    'quantity' => (int) $o->quantity,
                    'cost' => (float) $o->cost,
                ]),
                'adjustments' => $invoice->adjustments->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => $a->type->value,
                    'amount' => (float) $a->amount,
                    'percentage' => $a->percentage,
                    'note' => $a->note,
                ]),
                'transactions' => $invoice->transactions->sortByDesc('created_at')->map(fn ($x) => [
                    'id' => $x->id,
                    'type' => $x->type->value,
                    'method' => $x->method->value,
                    'amount' => (float) $x->amount,
                    'note' => $x->note,
                    'created_at' => $x->created_at?->toDateTimeString(),
                ])->values(),
            ],
        ]);
    }

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

    /**
     * Record the customer's approval of the estimate/invoice and clear the
     * approval gate on the ticket's billable work.
     */
    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);

        $invoice = $ticket->invoice()->first();

        if ($invoice === null) {
            return to_route('tickets.show', $ticket)
                ->withErrors(['approve' => 'Generate an invoice from the billable work before approving.']);
        }

        if ($invoice->status !== InvoiceStatus::Draft) {
            return to_route('tickets.show', $ticket)
                ->withErrors(['approve' => 'Only a draft invoice can be approved.']);
        }

        if ($invoice->total <= 0) {
            return to_route('tickets.show', $ticket)
                ->withErrors(['approve' => 'There is no billable work to approve.']);
        }

        DB::transaction(function () use ($invoice, $ticket) {
            $invoice->approve();
            $ticket->tasks()->billable()->update(['approved_at' => now()]);
        });

        return to_route('tickets.show', $ticket)
            ->with('success', 'Estimate approved. The ticket is cleared for repair.');
    }
}
