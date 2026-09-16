<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->user = User::factory()->admin()->create(['business_id' => $this->business->id]);
});

test('search returns matching tickets, customers, devices and products for the business', function () {
    $customer = Customer::factory()->create(['business_id' => $this->business->id, 'name' => 'Aurora Nguyen']);
    $device = Device::factory()->forCustomer($customer)->create([
        'business_id' => $this->business->id,
        'model' => 'Galaxy S24 Ultra',
        'imei' => '356938035643809',
    ]);
    $ticket = Ticket::factory()->forDevice($device)->create([
        'business_id' => $this->business->id,
        'title' => 'Battery drains fast',
    ]);
    Product::factory()->create([
        'business_id' => $this->business->id,
        'name' => 'S24 Battery',
        'sku' => 'BAT-S24-01',
    ]);

    expect($this->actingAs($this->user)->getJson(route('search', ['q' => 'aurora']))->json('customers.0.name'))->toBe('Aurora Nguyen');
    expect($this->actingAs($this->user)->getJson(route('search', ['q' => '356938035643809']))->json('devices.0.imei'))->toBe('356938035643809');
    expect($this->actingAs($this->user)->getJson(route('search', ['q' => 'BAT-S24-01']))->json('products.0.sku'))->toBe('BAT-S24-01');
    expect($this->actingAs($this->user)->getJson(route('search', ['q' => 'Battery drains']))->json('tickets.0.id'))->toBe($ticket->id);
});

test('search is scoped to the acting business and ignores other tenants', function () {
    $other = Business::factory()->create();
    Customer::factory()->create(['business_id' => $other->id, 'name' => 'Zephyr Unique']);

    $this->actingAs($this->user)
        ->getJson(route('search', ['q' => 'Zephyr Unique']))
        ->assertOk()
        ->assertJsonPath('customers', []);
});

test('short queries return empty groups', function () {
    $this->actingAs($this->user)
        ->getJson(route('search', ['q' => 'a']))
        ->assertOk()
        ->assertJsonPath('tickets', [])
        ->assertJsonPath('customers', []);
});

test('search requires authentication', function () {
    $this->getJson(route('search', ['q' => 'anything']))->assertStatus(401);
});
