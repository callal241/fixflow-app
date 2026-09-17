<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->user = User::factory()->admin()->create([
        'business_id' => $this->business->id,
    ]);
    // Authenticate first so factory-created records are stamped with the
    // business via the BelongsToBusiness creating hook.
    $this->actingAs($this->user);
});

test('ticket intake creates a new customer and device in one submit', function () {
    expect(Customer::count())->toBe(0);
    expect(Device::count())->toBe(0);

    $response = $this->actingAs($this->user)->post(route('tickets.store'), [
        'customer_id' => null,
        'new_customer' => [
            'name' => 'Walk In Sam',
            'company' => '',
            'email' => 'sam@example.com',
            'phone' => '+15550001111',
            'address' => '12 Main St',
        ],
        'device_id' => null,
        'new_device' => [
            'model' => 'iPhone 15 Pro',
            'model_number' => 'A2848',
            'brand' => 'Apple',
            'serial_number' => 'SN12345',
            'imei' => '350123456789012',
            'color' => 'Titanium Blue',
            'storage' => '256 GB',
            'carrier' => '',
            'type' => 'phone',
        ],
        'title' => 'Cracked screen',
        'description' => 'Display is shattered, still turns on',
        'internal_notes' => 'Likely a screen assembly swap.',
        'intake_type' => 'walk_in',
        'priority' => 'high',
        'due_date' => '2026-09-20',
        'assignee_id' => null,
    ]);

    $ticket = Ticket::first();
    expect($ticket)->not->toBeNull();
    expect($ticket->ticket_number)->toMatch('/^FF-\d{5}$/');

    $customer = Customer::first();
    expect($customer->name)->toBe('Walk In Sam');
    expect($customer->business_id)->toBe($this->business->id);

    $device = Device::first();
    expect($device->model)->toBe('iPhone 15 Pro');
    expect($device->customer_id)->toBe($customer->id);
    expect($device->business_id)->toBe($this->business->id);

    expect($ticket->device_id)->toBe($device->id);
    expect($ticket->title)->toBe('Cracked screen');
    expect($ticket->internal_notes)->toBe('Likely a screen assembly swap.');

    $response
        ->assertRedirect(route('tickets.show', $ticket))
        ->assertSessionHas('success');
});

test('ticket intake can reuse an existing customer and device', function () {
    $customer = Customer::factory()->create(['business_id' => $this->business->id]);
    $device = Device::factory()->forCustomer($customer)->create([
        'business_id' => $this->business->id,
    ]);

    $this->actingAs($this->user)->post(route('tickets.store'), [
        'customer_id' => $customer->id,
        'device_id' => $device->id,
        'title' => 'Won\'t charge',
        'intake_type' => 'walk_in',
        'priority' => 'medium',
    ]);

    expect(Customer::count())->toBe(1);
    expect(Device::count())->toBe(1);
    expect(Ticket::count())->toBe(1);
    expect(Ticket::first()->device_id)->toBe($device->id);
});

test('ticket intake fails when no customer or device is supplied', function () {
    $response = $this->actingAs($this->user)->post(route('tickets.store'), [
        'title' => 'No context',
        'intake_type' => 'walk_in',
        'priority' => 'low',
    ]);

    expect(Ticket::count())->toBe(0);
    // No customer_id and no device_id, so the nested "new customer" and
    // "new device" name/model fields become required via required_if.
    $response->assertSessionHasErrors(['new_customer.name', 'new_device.model']);
});

test('ticket detail renders for the owning business with device and customer', function () {
    $customer = Customer::factory()->create(['business_id' => $this->business->id]);
    $device = Device::factory()->forCustomer($customer)->create([
        'business_id' => $this->business->id,
    ]);
    $ticket = Ticket::factory()->forDevice($device)->create([
        'business_id' => $this->business->id,
    ]);

    $response = $this->actingAs($this->user)->get(route('tickets.show', $ticket));

    // Assert the Inertia props directly rather than raw HTML: some device
    // models contain a double quote (e.g. 'iMac 27"'), which the data-page
    // attribute stores as \&quot; — a form assertSee() cannot match.
    $response
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Tickets/Show')
            ->where('device.model', $device->model)
            ->where('customer.name', $customer->name));
});

test('other business staff cannot open a foreign ticket', function () {
    $customer = Customer::factory()->create(['business_id' => $this->business->id]);
    $device = Device::factory()->forCustomer($customer)->create([
        'business_id' => $this->business->id,
    ]);
    $ticket = Ticket::factory()->forDevice($device)->create([
        'business_id' => $this->business->id,
    ]);

    $otherBusiness = Business::factory()->create();
    $outsider = User::factory()->admin()->create(['business_id' => $otherBusiness->id]);

    $this->actingAs($outsider)->get(route('tickets.show', $ticket))
        ->assertStatus(403);
});

test('repair work and parts are recorded against a ticket', function () {
    $customer = Customer::factory()->create(['business_id' => $this->business->id]);
    $device = Device::factory()->forCustomer($customer)->create([
        'business_id' => $this->business->id,
    ]);
    $ticket = Ticket::factory()->forDevice($device)->create([
        'business_id' => $this->business->id,
    ]);

    // Log a billable repair task.
    $this->actingAs($this->user)->post(route('tickets.tasks.store', $ticket), [
        'type' => 'repair',
        'note' => 'Replaced battery',
        'cost' => 85,
        'is_billable' => true,
        'status' => 'completed',
    ]);

    // Sell a catalog part against the ticket.
    $product = \App\Models\Product::factory()->inStock()->create([
        'business_id' => $this->business->id,
    ]);
    $this->actingAs($this->user)->post(route('tickets.orders.store', $ticket), [
        'product_id' => $product->id,
        'quantity' => 1,
        'is_billable' => true,
    ]);

    expect($ticket->tasks()->count())->toBe(1);
    expect((float) $ticket->tasks()->first()->cost)->toBe(85.0);
    expect((float) $ticket->tasks()->billable()->sum('cost'))->toBe(85.0);
    expect((float) $ticket->orders()->billable()->sum('cost'))->toBe((float) $product->price);
});

test('invoice is generated from billable work and a payment settles it', function () {
    $customer = Customer::factory()->create(['business_id' => $this->business->id]);
    $device = Device::factory()->forCustomer($customer)->create([
        'business_id' => $this->business->id,
    ]);
    $ticket = Ticket::factory()->forDevice($device)->create([
        'business_id' => $this->business->id,
    ]);

    Task::factory()->forTicket($ticket)->billable(85)->create(['business_id' => $this->business->id]);
    $ticket->orders()->create([
        'name' => 'Screen assembly',
        'quantity' => 1,
        'price' => 120,
        'cost' => 120,
        'is_billable' => true,
    ]);

    $this->actingAs($this->user)->post(route('tickets.invoice.store', $ticket));

    $invoice = Invoice::where('ticket_id', $ticket->id)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->task_total)->toBe(85.0);
    expect($invoice->order_total)->toBe(120.0);
    expect($invoice->total)->toBe(205.0);
    expect($invoice->balance)->toBe(205.0);
    expect($invoice->status)->toBe(InvoiceStatus::Draft);

    $this->actingAs($this->user)->post(route('tickets.invoice.transactions.store', $ticket), [
        'amount' => 205,
        'method' => 'card',
        'type' => 'payment',
    ]);

    $invoice->refresh();
    expect($invoice->paid_amount)->toBe(205.0);
    expect($invoice->balance)->toBe(0.0);
    expect($invoice->status)->toBe(InvoiceStatus::Paid);
});

test('a payment cannot exceed the invoice balance', function () {
    $customer = Customer::factory()->create(['business_id' => $this->business->id]);
    $device = Device::factory()->forCustomer($customer)->create([
        'business_id' => $this->business->id,
    ]);
    $ticket = Ticket::factory()->forDevice($device)->create([
        'business_id' => $this->business->id,
    ]);
    Task::factory()->forTicket($ticket)->billable(50)->create(['business_id' => $this->business->id]);

    $this->actingAs($this->user)->post(route('tickets.invoice.store', $ticket));

    $this->actingAs($this->user)->post(route('tickets.invoice.transactions.store', $ticket), [
        'amount' => 999,
        'method' => 'card',
        'type' => 'payment',
    ])->assertSessionHasErrors('amount');
});

test('ticket update changes status and priority', function () {
    $customer = Customer::factory()->create(['business_id' => $this->business->id]);
    $device = Device::factory()->forCustomer($customer)->create([
        'business_id' => $this->business->id,
    ]);
    $ticket = Ticket::factory()->forDevice($device)->create([
        'business_id' => $this->business->id,
    ]);

    $this->actingAs($this->user)->put(route('tickets.update', $ticket), [
        'title' => $ticket->title,
        'description' => $ticket->description,
        'internal_notes' => 'Updated notes',
        'status' => 'in_progress',
        'priority' => 'urgent',
        'due_date' => '2026-09-25',
        'assignee_id' => $this->user->id,
    ]);

    $ticket->refresh();
    expect($ticket->status->value)->toBe('in_progress');
    expect($ticket->priority->value)->toBe('urgent');
    expect($ticket->assignee_id)->toBe($this->user->id);
});
