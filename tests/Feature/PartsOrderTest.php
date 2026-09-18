<?php

use App\Enums\OrderStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Order;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->user = User::factory()->admin()->create(['business_id' => $this->business->id]);

    $this->customer = Customer::factory()->create(['business_id' => $this->business->id]);
    $this->device = Device::factory()->forCustomer($this->customer)->create(['business_id' => $this->business->id]);
    $this->ticket = Ticket::factory()->forDevice($this->device)->create(['business_id' => $this->business->id]);

    $this->otherBusiness = Business::factory()->create();
    $this->outsider = User::factory()->admin()->create(['business_id' => $this->otherBusiness->id]);
});

/*
|--------------------------------------------------------------------------
| FIND PARTS (free-form supplier line, no catalog product)
|--------------------------------------------------------------------------
*/

test('find parts: a free-form supplier part can be added to a ticket', function () {
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'name' => 'iPhone 13 Screen (OLED)',
        'supplier' => 'iFixit Parts Co.',
        'url' => 'https://www.ifixit.com/part/iphone-13-screen',
        'quantity' => 1,
        'price' => 42.50,
    ]);

    $order = $this->ticket->orders()->first();
    expect($order)->not->toBeNull()
        ->and($order->name)->toBe('iPhone 13 Screen (OLED)')
        ->and($order->supplier)->toBe('iFixit Parts Co.')
        ->and($order->url)->toBe('https://www.ifixit.com/part/iphone-13-screen')
        ->and($order->product_id)->toBeNull()
        ->and($order->is_purchase)->toBeFalse()
        ->and($order->is_billable)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::New)
        ->and($order->price)->toEqualWithDelta(42.50, 0.001)
        ->and($order->cost)->toEqualWithDelta(42.50, 0.001);
});

test('find parts: a part without a name is rejected when no product is picked', function () {
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'quantity' => 1,
        'price' => 10,
    ])->assertSessionHasErrors('name');

    expect($this->ticket->orders()->count())->toBe(0);
});

test('find parts: a part can be flagged as a purchase with no catalog link', function () {
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'name' => 'Sourcing batch of 20 batteries',
        'quantity' => 20,
        'price' => 6.00,
        'is_purchase' => true,
    ]);

    $order = $this->ticket->orders()->first();
    expect($order->is_purchase)->toBeTrue()
        ->and($order->cost)->toEqualWithDelta(120.0, 0.001);
});

test('find parts: a supplier part flows into the invoice total', function () {
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'name' => 'Replacement hinge',
        'quantity' => 1,
        'price' => 15.00,
    ]);

    $this->actingAs($this->user)->post(route('tickets.invoice.store', $this->ticket));

    $invoice = $this->ticket->invoice()->first();
    expect((float) $invoice->order_total)->toEqualWithDelta(15.0, 0.001);
});

/*
|--------------------------------------------------------------------------
| OWN-STOCK PART (catalog product pulled from the shelf) -> reservation
|--------------------------------------------------------------------------
*/

test('adding an own-stock part reserves stock (available drops, on-hand holds)', function () {
    $product = Product::factory()->create(['business_id' => $this->business->id, 'name' => 'Screen', 'price' => 50, 'stock' => 10, 'reserved_stock' => 0]);

    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 3,
    ]);

    $product->refresh();
    expect((int) $product->stock)->toBe(10)              // on-hand unchanged until received
        ->and((int) $product->reserved_stock)->toBe(3)
        ->and((int) $product->available)->toBe(7);
});

test('adding an own-stock part beyond available stock is rejected', function () {
    $product = Product::factory()->create(['business_id' => $this->business->id, 'name' => 'Screen', 'price' => 50, 'stock' => 2, 'reserved_stock' => 0]);

    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 5,
    ])->assertSessionHasErrors('product_id');

    expect($this->ticket->orders()->count())->toBe(0)
        ->and((int) $product->refresh()->reserved_stock)->toBe(0);
});

test('a purchase of a catalog product is exempt from the availability guard', function () {
    $product = Product::factory()->create(['business_id' => $this->business->id, 'name' => 'Screen', 'price' => 50, 'stock' => 0, 'reserved_stock' => 0]);

    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 4,
        'is_purchase' => true,
        'supplier' => 'Supplier X',
    ])->assertSessionHasNoErrors();

    $order = $this->ticket->orders()->first();
    expect($order)->not->toBeNull()
        ->and($order->product_id)->toBe($product->id)
        ->and($order->is_purchase)->toBeTrue()
        ->and((int) $product->refresh()->reserved_stock)->toBe(0); // purchases never reserve
});

/*
|--------------------------------------------------------------------------
| RECEIVING
|--------------------------------------------------------------------------
*/

test('receiving an own-stock part consumes the reservation', function () {
    $product = Product::factory()->create(['business_id' => $this->business->id, 'name' => 'Screen', 'price' => 50, 'stock' => 10, 'reserved_stock' => 0]);

    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 3,
    ]);
    $order = $this->ticket->orders()->first();

    $this->actingAs($this->user)->patch(route('tickets.orders.receive', ['ticket' => $this->ticket, 'order' => $order]));

    $order->refresh();
    $product->refresh();
    expect($order->status)->toBe(OrderStatus::Received)
        ->and($order->received_at)->not->toBeNull()
        ->and((int) $product->stock)->toBe(7)             // 10 - 3 consumed
        ->and((int) $product->reserved_stock)->toBe(0)    // reservation cleared
        ->and((int) $product->available)->toBe(7);
});

test('receiving a purchase of a catalog product restocks it', function () {
    $product = Product::factory()->create(['business_id' => $this->business->id, 'name' => 'Screen', 'price' => 50, 'stock' => 0, 'reserved_stock' => 0]);

    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 6,
        'is_purchase' => true,
    ]);
    $order = $this->ticket->orders()->first();

    $this->actingAs($this->user)->patch(route('tickets.orders.receive', ['ticket' => $this->ticket, 'order' => $order]));

    $product->refresh();
    expect($order->refresh()->status)->toBe(OrderStatus::Received)
        ->and((int) $product->stock)->toBe(6)             // restocked
        ->and((int) $product->reserved_stock)->toBe(0);
});

test('receiving a free-form supplier part marks it received without touching stock', function () {
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'name' => 'Rare capacitor',
        'quantity' => 1,
        'price' => 4.00,
    ]);
    $order = $this->ticket->orders()->first();

    $this->actingAs($this->user)->patch(route('tickets.orders.receive', ['ticket' => $this->ticket, 'order' => $order]));

    expect($order->refresh()->status)->toBe(OrderStatus::Received)
        ->and($order->received_at)->not->toBeNull();
});

test('a cancelled part cannot be received', function () {
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'name' => 'Part A',
        'quantity' => 1,
        'price' => 5,
    ]);
    $order = $this->ticket->orders()->first();

    $this->actingAs($this->user)->patch(route('tickets.orders.cancel', ['ticket' => $this->ticket, 'order' => $order]));
    $this->actingAs($this->user)->patch(route('tickets.orders.receive', ['ticket' => $this->ticket, 'order' => $order]))
        ->assertSessionHasErrors('order');

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
});

/*
|--------------------------------------------------------------------------
| CANCELLING (spec: cancelled orders are non-billable)
|--------------------------------------------------------------------------
*/

test('cancelling a part makes it non-billable and releases its reservation', function () {
    $product = Product::factory()->create(['business_id' => $this->business->id, 'name' => 'Screen', 'price' => 50, 'stock' => 10, 'reserved_stock' => 0]);

    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 4,
    ]);
    $order = $this->ticket->orders()->first();

    $this->actingAs($this->user)->patch(route('tickets.orders.cancel', ['ticket' => $this->ticket, 'order' => $order]));

    $order->refresh();
    $product->refresh();
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->is_billable)->toBeFalse()
        ->and((int) $product->reserved_stock)->toBe(0)    // released
        ->and((int) $product->stock)->toBe(10)
        ->and((int) $product->available)->toBe(10);
});

test('a received part cannot be cancelled', function () {
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'name' => 'Part B',
        'quantity' => 1,
        'price' => 5,
    ]);
    $order = $this->ticket->orders()->first();

    $this->actingAs($this->user)->patch(route('tickets.orders.receive', ['ticket' => $this->ticket, 'order' => $order]));
    $this->actingAs($this->user)->patch(route('tickets.orders.cancel', ['ticket' => $this->ticket, 'order' => $order]))
        ->assertSessionHasErrors('order');

    expect($order->refresh()->status)->toBe(OrderStatus::Received)
        ->and($order->is_billable)->toBeTrue();
});

test('deleting a received or cancelled part does not move stock twice', function () {
    $product = Product::factory()->create(['business_id' => $this->business->id, 'name' => 'Screen', 'price' => 50, 'stock' => 10, 'reserved_stock' => 0]);

    // A cancelled part: its reservation was already released on cancel.
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
    $cancelled = $this->ticket->orders()->first();
    $this->actingAs($this->user)->patch(route('tickets.orders.cancel', ['ticket' => $this->ticket, 'order' => $cancelled]));

    // A received own-stock part: its reservation was consumed on receive.
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);
    $received = $this->ticket->orders()->whereKeyNot($cancelled->id)->first();
    $this->actingAs($this->user)->patch(route('tickets.orders.receive', ['ticket' => $this->ticket, 'order' => $received]));
    expect($received->refresh()->status)->toBe(OrderStatus::Received); // sanity: this one really was received

    // Now delete both: neither should move stock again.
    $this->actingAs($this->user)->delete(route('tickets.orders.destroy', ['ticket' => $this->ticket, 'order' => $cancelled]));
    $this->actingAs($this->user)->delete(route('tickets.orders.destroy', ['ticket' => $this->ticket, 'order' => $received]));

    $product->refresh();
    // start 10; reserve 2 (avail 8); cancel (release 2, avail 10);
    // reserve 1 (avail 9); receive (consume 1 -> stock 9, reserved 0).
    // Deletions of terminal lines change nothing.
    expect((int) $product->stock)->toBe(9)
        ->and((int) $product->reserved_stock)->toBe(0)
        ->and($this->ticket->orders()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| RESERVED / AVAILABLE MATH
|--------------------------------------------------------------------------
*/

test('available never goes below zero and reservations clamp to on-hand', function () {
    $product = Product::factory()->create(['business_id' => $this->business->id, 'name' => 'Screen', 'price' => 50, 'stock' => 3, 'reserved_stock' => 0]);

    // Reserve 2 -> available 1
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
    expect((int) $product->refresh()->available)->toBe(1);

    // Reserve the last 1 -> available 0
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);
    expect((int) $product->refresh()->available)->toBe(0);

    // Cannot reserve beyond available.
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertSessionHasErrors('product_id');

    expect((int) $product->refresh()->reserved_stock)->toBe(3) // clamped at on-hand, never above
        ->and((int) $product->available)->toBe(0);
});

test('available is the public accessor on Product', function () {
    $product = Product::factory()->create(['business_id' => $this->business->id, 'stock' => 9, 'reserved_stock' => 4]);
    expect((int) $product->available)->toBe(5)
        ->and($product->hasAvailable(5))->toBeTrue()
        ->and($product->hasAvailable(6))->toBeFalse();

    $edge = Product::factory()->create(['business_id' => $this->business->id, 'stock' => 2, 'reserved_stock' => 9]);
    expect((int) $edge->available)->toBe(0); // never negative
});

test('receiving one concurrent line only consumes its own reservation', function () {
    $product = Product::factory()->create(['business_id' => $this->business->id, 'name' => 'Screen', 'price' => 50, 'stock' => 10, 'reserved_stock' => 0]);

    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 4,
    ]);
    $lineA = $this->ticket->orders()->first();

    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'product_id' => $product->id,
        'quantity' => 3,
    ]);
    $lineB = $this->ticket->orders()->whereKeyNot($lineA->id)->first();

    expect((int) $product->refresh()->reserved_stock)->toBe(7);

    // Receive line A: consumes only its 4, line B's 3 stays reserved.
    $this->actingAs($this->user)->patch(route('tickets.orders.receive', ['ticket' => $this->ticket, 'order' => $lineA]));

    $product->refresh();
    expect((int) $product->stock)->toBe(6)        // 10 - 4
        ->and((int) $product->reserved_stock)->toBe(3) // line B untouched
        ->and((int) $product->available)->toBe(3);

    // Releasing line B (delete) restores availability to the full on-hand.
    $this->actingAs($this->user)->delete(route('tickets.orders.destroy', ['ticket' => $this->ticket, 'order' => $lineB]));

    $product->refresh();
    expect((int) $product->stock)->toBe(6)
        ->and((int) $product->reserved_stock)->toBe(0)
        ->and((int) $product->available)->toBe(6);
});

/*
|--------------------------------------------------------------------------
| TENANT ISOLATION
|--------------------------------------------------------------------------
*/

test('a user from another business cannot add, receive, or cancel a part', function () {
    $this->actingAs($this->user)->post(route('tickets.orders.store', $this->ticket), [
        'name' => 'Part C',
        'quantity' => 1,
        'price' => 5,
    ]);
    $order = $this->ticket->orders()->first();

    $this->actingAs($this->outsider)->post(route('tickets.orders.store', $this->ticket), [
        'name' => 'Intruder part',
        'quantity' => 1,
        'price' => 5,
    ])->assertStatus(403);

    $this->actingAs($this->outsider)->patch(route('tickets.orders.receive', ['ticket' => $this->ticket, 'order' => $order]))
        ->assertStatus(403);

    $this->actingAs($this->outsider)->patch(route('tickets.orders.cancel', ['ticket' => $this->ticket, 'order' => $order]))
        ->assertStatus(403);

    expect($this->ticket->orders()->count())->toBe(1)
        ->and($order->refresh()->status)->toBe(OrderStatus::New);
});

test('a part action for an order that is not on the ticket is a 404', function () {
    $foreignTicket = Ticket::factory()->forDevice($this->device)->create(['business_id' => $this->business->id]);
    $this->actingAs($this->user)->post(route('tickets.orders.store', $foreignTicket), [
        'name' => 'Other ticket part',
        'quantity' => 1,
        'price' => 5,
    ]);
    $foreignOrder = $foreignTicket->orders()->first();

    $this->actingAs($this->user)->patch(route('tickets.orders.receive', ['ticket' => $this->ticket, 'order' => $foreignOrder]))
        ->assertStatus(404);
});
