<?php

use App\Enums\TaskType;
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
    $this->otherBusiness = Business::factory()->create();
    $this->outsider = User::factory()->admin()->create(['business_id' => $this->otherBusiness->id]);
});

function ticketFor(int $businessId, array $attrs = []): Ticket
{
    $customer = Customer::factory()->create(['name' => 'Bill Customer']);
    $device = Device::factory()->forCustomer($customer)->create();

    return Ticket::factory()
        ->forDevice($device)
        ->create(array_merge(['business_id' => $businessId], $attrs));
}

function invoiceFor(int $businessId, array $attrs = []): Invoice
{
    $ticket = ticketFor($businessId);

    return Invoice::factory()
        ->forTicket($ticket)
        ->create(array_merge(['business_id' => $businessId], $attrs));
}

test('the invoice list shows only the acting business invoices', function () {
    $ours = invoiceFor($this->business->id);
    invoiceFor($this->otherBusiness->id);

    $this->actingAs($this->user)
        ->get(route('invoices.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->where('invoices', fn ($invoices) => $invoices->count() === 1
                && $invoices->first()['id'] === $ours->id));
});

test('the invoice detail renders the billable line items and totals', function () {
    $invoice = invoiceFor($this->business->id);
    $ticket = $invoice->ticket;

    Task::factory()->forTicket($ticket)
        ->ofType(TaskType::Diagnostic)
        ->billable(100)
        ->withNote('Screen replacement')
        ->create(['business_id' => $this->business->id]);

    Transaction::factory()->forInvoice($invoice)
        ->payment(40)
        ->create(['business_id' => $this->business->id]);

    $this->actingAs($this->user)
        ->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Invoices/Show')
            ->where('invoice.id', $invoice->id)
            ->where('invoice.business', $this->business->name)
            ->where('invoice.customer.name', 'Bill Customer')
            ->where('invoice.tasks.0.note', 'Screen replacement')
            ->where('invoice.tasks.0.cost', fn ($cost) => abs($cost - 100.0) < 0.001)
            ->where('invoice.transactions', fn ($txs) => $txs->count() === 1
                && abs($txs->first()['amount'] - 40.0) < 0.001
                && $txs->first()['type'] === 'payment'));
});

test('staff from another business cannot open a foreign invoice', function () {
    $invoice = invoiceFor($this->business->id);

    $this->actingAs($this->outsider)
        ->get(route('invoices.show', $invoice))
        ->assertStatus(403);
});

test('invoice pages require authentication', function () {
    $invoice = invoiceFor($this->business->id);

    $this->get(route('invoices.index'))->assertRedirect(route('login'));
    $this->get(route('invoices.show', $invoice))->assertRedirect(route('login'));
});
