<?php

use App\Enums\InvoiceStatus;
use App\Models\Business;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->user = User::factory()->admin()->create(['business_id' => $this->business->id]);
});

function makeApproveTestTicket(int $businessId, float $cost = 100.0, bool $billable = true): array
{
    $ticket = Ticket::factory()->create(['business_id' => $businessId]);
    // Start from unapproved work: billable, but the customer has not yet
    // cleared it (the factory default seeds demo data as already approved).
    Task::factory()->for($ticket)->create([
        'business_id' => $businessId,
        'cost' => $cost,
        'is_billable' => $billable,
        'approved_at' => null,
    ]);

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

test('approving a draft estimate marks the invoice approved and sent', function () {
    [$ticket, $invoice] = makeApproveTestTicket($this->business->id, 250.0);
    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->approved_at)->toBeNull()
        ->and($ticket->tasks()->billable()->approved()->count())->toBe(0);

    $this->actingAs($this->user)
        ->post(route('tickets.invoice.approve', $ticket))
        ->assertRedirect(route('tickets.show', $ticket))
        ->assertSessionHas('success');

    $invoice->refresh();
    expect($invoice->approved_at)->not->toBeNull()
        ->and($invoice->isApproved())->toBeTrue()
        ->and($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and($invoice->paid_amount)->toBe(0.0);

    // Billable work is cleared for repair.
    expect($ticket->tasks()->billable()->approved()->count())->toBe(1);
});

test('approval re-syncs the total when work is added after the estimate was generated', function () {
    [$ticket, $invoice] = makeApproveTestTicket($this->business->id, 100.0);

    // Technician adds more billable work before the customer approves.
    Task::factory()->for($ticket)->create([
        'business_id' => $this->business->id,
        'cost' => 75.0,
        'is_billable' => true,
    ]);

    $this->actingAs($this->user)->post(route('tickets.invoice.approve', $ticket));

    $invoice->refresh();
    expect($invoice->approved_at)->not->toBeNull()
        ->and($invoice->task_total)->toBe(175.0)
        ->and($invoice->total)->toBe(175.0)
        ->and($invoice->status)->toBe(InvoiceStatus::Sent);
});

test('an invoice without billable work cannot be approved', function () {
    [$ticket, $invoice] = makeApproveTestTicket($this->business->id, 0.0, billable: false);
    expect($invoice->total)->toBe(0.0);

    $this->actingAs($this->user)
        ->post(route('tickets.invoice.approve', $ticket))
        ->assertRedirect(route('tickets.show', $ticket))
        ->assertSessionHasErrors('approve');

    expect($invoice->refresh()->approved_at)->toBeNull();
});

test('a ticket without an invoice cannot be approved', function () {
    $ticket = Ticket::factory()->create(['business_id' => $this->business->id]);

    $this->actingAs($this->user)
        ->post(route('tickets.invoice.approve', $ticket))
        ->assertRedirect(route('tickets.show', $ticket))
        ->assertSessionHasErrors('approve');

    expect($ticket->invoice()->exists())->toBeFalse();
});

test('approval is idempotent and keeps the first approval timestamp', function () {
    [$ticket, $invoice] = makeApproveTestTicket($this->business->id, 100.0);

    $this->actingAs($this->user)->post(route('tickets.invoice.approve', $ticket));
    $firstApproval = $invoice->refresh()->approved_at;
    expect($firstApproval)->not->toBeNull();

    // Re-approve after a delay: timestamp must not move.
    Carbon::setTestNow($firstApproval->copy()->addHour());
    try {
        $this->actingAs($this->user)->post(route('tickets.invoice.approve', $ticket));
    } finally {
        Carbon::setTestNow();
    }

    expect($invoice->refresh()->approved_at)->toEqual($firstApproval);
});

test('other business staff cannot approve a foreign ticket estimate', function () {
    [$ticket] = makeApproveTestTicket($this->business->id, 100.0);
    $otherBusiness = Business::factory()->create();
    $outsider = User::factory()->admin()->create(['business_id' => $otherBusiness->id]);

    $this->actingAs($outsider)
        ->post(route('tickets.invoice.approve', $ticket))
        ->assertStatus(403);

    expect($ticket->invoice()->first()->approved_at)->toBeNull();
});

test('an already paid invoice cannot be approved', function () {
    [$ticket, $invoice] = makeApproveTestTicket($this->business->id, 100.0);
    $invoice->forceFill(['paid_amount' => 100.0, 'status' => InvoiceStatus::Paid])->save();

    $this->actingAs($this->user)
        ->post(route('tickets.invoice.approve', $ticket))
        ->assertRedirect(route('tickets.show', $ticket))
        ->assertSessionHasErrors('approve');

    expect($invoice->refresh()->approved_at)->toBeNull();
});
