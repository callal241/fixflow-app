<?php

use App\Enums\InvoiceStatus;
use App\Enums\TransactionMethod;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Payments\PaymentProviderRegistry;
use App\Exceptions\UnknownProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->user = User::factory()->admin()->create(['business_id' => $this->business->id]);
});

function makeInvoicedTicket(int $businessId, float $cost = 100.0): array
{
    $ticket = Ticket::factory()->create(['business_id' => $businessId]);
    Task::factory()->for($ticket)->create([
        'business_id' => $businessId,
        'cost' => $cost,
        'is_billable' => true,
    ]);

    // Generate the invoice the same way the UI does.
    $invoice = new Invoice;
    $invoice->forceFill(['ticket_id' => $ticket->id]);
    $invoice->business_id = $businessId;
    $invoice
        ->fillTaskTotal()
        ->fillOrderTotal()
        ->fillAdjustmentAmounts()
        ->syncTotal()
        ->fillStatus();
    $invoice->due_date = now()->addWeeks(2);
    $invoice->save();

    return [$ticket, $invoice->refresh()];
}

test('a card payment is captured through the active provider and the invoice is marked paid', function () {
    [$ticket, $invoice] = makeInvoicedTicket($this->business->id, 100.0);
    expect($invoice->balance)->toBe(100.0);

    $this->actingAs($this->user)->post(route('tickets.invoice.transactions.store', $ticket), [
        'amount' => 100.0,
        'method' => 'card',
        'type' => 'payment',
    ])->assertRedirect(route('tickets.show', $ticket));

    $invoice->refresh();
    expect($invoice->paid_amount)->toBe(100.0)
        ->and($invoice->balance)->toBe(0.0)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid);

    $transaction = $invoice->transactions()->first();
    expect($transaction->method)->toBe(TransactionMethod::Card)
        ->and($transaction->note)->toContain('ref');
});

test('a payment cannot exceed the outstanding balance', function () {
    [$ticket] = makeInvoicedTicket($this->business->id, 50.0);

    $this->actingAs($this->user)->post(route('tickets.invoice.transactions.store', $ticket), [
        'amount' => 200.0,
        'method' => 'cash',
        'type' => 'payment',
    ])->assertSessionHasErrors('amount');

    expect(Invoice::find(1)?->transactions()->count())->toBe(0);
});

test('a refund does not re-run the provider and cannot exceed paid', function () {
    [$ticket, $invoice] = makeInvoicedTicket($this->business->id, 100.0);

    $this->actingAs($this->user)->post(route('tickets.invoice.transactions.store', $ticket), [
        'amount' => 100.0, 'method' => 'card', 'type' => 'payment',
    ]);
    $invoice->refresh();
    expect($invoice->paid_amount)->toBe(100.0);

    // Refund more than paid is rejected.
    $this->actingAs($this->user)->post(route('tickets.invoice.transactions.store', $ticket), [
        'amount' => 150.0, 'method' => 'card', 'type' => 'refund',
    ])->assertSessionHasErrors('amount');

    // A refund up to paid is recorded, without a provider reference in the note.
    $this->actingAs($this->user)->post(route('tickets.invoice.transactions.store', $ticket), [
        'amount' => 100.0, 'method' => 'card', 'type' => 'refund',
    ])->assertRedirect(route('tickets.show', $ticket));

    $refund = $invoice->transactions()->ofMethod(TransactionMethod::Card)->get()->last();
    expect($refund->type->value)->toBe('refund')
        ->and((string) ($refund->note ?? ''))->not->toContain('ref');
});

test('a payment requires an invoice first', function () {
    $ticket = Ticket::factory()->create(['business_id' => $this->business->id]);

    $this->actingAs($this->user)->post(route('tickets.invoice.transactions.store', $ticket), [
        'amount' => 10.0, 'method' => 'cash', 'type' => 'payment',
    ])->assertSessionHasErrors('amount');
});

test('the registry resolves the counter provider by default and on fallback', function () {
    $registry = app(PaymentProviderRegistry::class);

    expect($registry->for($this->business)->id())->toBe('counter');

    // A business pointing at an unknown/unconfigured provider falls back to counter.
    $this->business->forceFill(['payment_provider_id' => 'nope'])->save();
    expect($registry->for($this->business)->id())->toBe('counter');

    expect($registry->options())->toHaveKey('counter');
    expect($registry->all())->toHaveKey('counter');
});

test('the registry resolves null for unknown ids so checkout can fall back', function () {
    $registry = app(PaymentProviderRegistry::class);

    expect($registry->resolve('does-not-exist'))->toBeNull()
        ->and($registry->resolve(null))->toBeNull()
        ->and($registry->resolve('counter')->id())->toBe('counter');
});

test('the registry throws for a registered id whose class is invalid', function () {
    config(['payments.providers.broken' => ['label' => 'Broken', 'class' => \stdClass::class]]);

    $this->expectException(UnknownProviderException::class);
    app(PaymentProviderRegistry::class)->resolve('broken');
});

test('business settings persist the payment provider choice', function () {
    $this->actingAs($this->user)->put(route('business.update'), [
        'payment_provider_id' => 'counter',
    ])->assertRedirect(route('business.edit'));

    expect($this->business->fresh()->payment_provider_id)->toBe('counter');

    // Unknown ids are normalised back to the default (null).
    $this->actingAs($this->user)->put(route('business.update'), [
        'payment_provider_id' => 'ghost',
    ]);
    expect($this->business->fresh()->payment_provider_id)->toBeNull();
});
