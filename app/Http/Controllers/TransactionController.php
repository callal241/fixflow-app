<?php

namespace App\Http\Controllers;

use App\Enums\TransactionMethod;
use App\Enums\TransactionType;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Payments\PaymentProviderRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function __construct(
        private readonly PaymentProviderRegistry $providers,
    ) {
    }

    /**
     * Record a payment or refund against the ticket's invoice.
     *
     * The capture is performed through the business's active payment provider
     * (see App\Payments). The provider is vendor-agnostic; the ledger stores
     * the provider's reference so the recorded transaction traces back to the
     * real capture.
     */
    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', Rule::in(TransactionMethod::values())],
            'type' => ['required', 'string', Rule::in(TransactionType::values())],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $invoice = $ticket->invoice;
        if ($invoice === null) {
            return to_route('tickets.show', $ticket)
                ->withErrors(['amount' => 'Generate the invoice first.']);
        }

        $amount = round((float) $validated['amount'], 2);
        $method = TransactionMethod::from($validated['method']);
        $isRefund = $validated['type'] === TransactionType::Refund->value;

        // A payment cannot exceed the current balance; a refund cannot exceed paid.
        if (! $isRefund && $amount > (float) $invoice->balance) {
            return to_route('tickets.show', $ticket)
                ->withErrors(['amount' => "Balance due is \${number_format((float) $invoice->balance, 2)}."])
                ->withInput();
        }
        if ($isRefund && $amount > (float) $invoice->paid_amount) {
            return to_route('tickets.show', $ticket)
                ->withErrors(['amount' => "Only \${number_format((float) $invoice->paid_amount, 2)} paid to refund."])
                ->withInput();
        }

        // Perform the capture through the active provider. Refunds are recorded
        // without a re-capture (money already changed hands), so we only charge
        // for new payments.
        $reference = null;
        if (! $isRefund) {
            $provider = $this->providers->for($ticket->business);

            if (! $provider->supports($method)) {
                return to_route('tickets.show', $ticket)
                    ->withErrors(['method' => sprintf('%s cannot take %s payments.', $provider->name(), $method->value)])
                    ->withInput();
            }

            $result = $provider->charge($amount, $method, $ticket->business?->currency, [
                'invoice_id' => $invoice->id,
                'ticket_id' => $ticket->id,
            ]);

            if (! $result->success) {
                return to_route('tickets.show', $ticket)
                    ->withErrors(['amount' => $result->failureReason ?? 'Payment was not completed.'])
                    ->withInput();
            }

            $reference = $result->reference;
        }

        $note = trim(($validated['note'] ?? '').' '.($reference ? "(ref {$reference})" : ''));

        $invoice->transactions()->create([
            'amount' => $amount,
            'method' => $method,
            'type' => $validated['type'],
            'note' => $note !== '' ? $note : null,
        ]);

        // TransactionObserver refreshes paid/refunded + status.
        $invoice->refresh();

        return to_route('tickets.show', $ticket)
            ->with('success', sprintf(
                '%s of $%s recorded.',
                $isRefund ? 'Refund' : 'Payment',
                number_format($amount, 2),
            ));
    }

    /**
     * Remove a transaction (payment/refund) from the invoice.
     */
    public function destroy(Request $request, Ticket $ticket, Transaction $transaction): RedirectResponse
    {
        abort_unless($ticket->business_id === $request->user()->business_id, 403);
        abort_unless($transaction->invoice?->ticket_id === $ticket->id, 404);

        $transaction->delete();

        $ticket->invoice?->refresh();

        return to_route('tickets.show', $ticket)
            ->with('success', 'Transaction removed.');
    }
}
