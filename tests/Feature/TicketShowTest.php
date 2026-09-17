<?php

use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->user = User::factory()->admin()->create(['business_id' => $this->business->id]);
});

/**
 * Build a ticket that has an invoice AND at least one recorded transaction,
 * mirroring the end of a real repair lifecycle (work -> invoice -> payment).
 */
function paidTicket(int $businessId): array
{
    $customer = Customer::factory()->create(['business_id' => $businessId]);
    $device = Device::factory()->forCustomer($customer)->create(['business_id' => $businessId]);
    $ticket = Ticket::factory()->forDevice($device)->create(['business_id' => $businessId]);

    Task::factory()->for($ticket)->create([
        'business_id' => $businessId,
        'cost' => 150.0,
        'is_billable' => true,
    ]);

    $invoice = new Invoice;
    $invoice->forceFill(['ticket_id' => $ticket->id]);
    $invoice->business_id = $businessId;
    $invoice->fillTaskTotal()->fillOrderTotal()->fillAdjustmentAmounts()->syncTotal()->fillStatus();
    $invoice->due_date = now()->addWeeks(2);
    $invoice->save();

    Transaction::factory()
        ->forInvoice($invoice->refresh())
        ->payment((float) $invoice->balance)
        ->create(['business_id' => $businessId]);

    return [$ticket, $invoice->refresh()];
}

test('a ticket page renders when its invoice has recorded transactions', function () {
    [$ticket, $invoice] = paidTicket($this->business->id);

    // Sanity: the preconditions that used to blow up the page are in place.
    expect($invoice->transactions)->toHaveCount(1)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and((float) $invoice->paid_amount)->toBeGreaterThan(0.0);

    // Regression: show() mapped over the loaded transactions collection. A
    // query-only method (latest()) on a Collection throws and 500s the page.
    $this->actingAs($this->user)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tickets/Show')
            ->where('invoice.status', InvoiceStatus::Paid->value)
            ->where('invoice.transactions', function ($txns) use ($invoice) {
                // $txns is a Collection of transaction arrays (newest first).
                return $txns->count() === 1
                    && (float) $txns->first()['amount'] === (float) $invoice->paid_amount
                    && $txns->first()['type'] === 'payment';
            }));
});

test('a ticket page renders after a payment and a refund', function () {
    [$ticket, $invoice] = paidTicket($this->business->id);

    // A second transaction (refund) exercises the sort path with >1 item.
    Transaction::factory()
        ->forInvoice($invoice)
        ->refund(25.0)
        ->create(['business_id' => $this->business->id]);

    $invoice->refresh();

    $this->actingAs($this->user)
        ->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('invoice.transactions', fn ($txns) => $txns->count() === 2));
});
