<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->user = User::factory()->admin()->create(['business_id' => $this->business->id]);
});

function deviceWithTicket(int $businessId): Device
{
    $customer = Customer::factory()->create(['business_id' => $businessId, 'name' => 'Dana Owner']);
    $device = Device::factory()->forCustomer($customer)->create(['business_id' => $businessId]);

    Ticket::factory()->forDevice($device)->create([
        'business_id' => $businessId,
        'title' => 'Cracked screen',
    ]);

    return $device;
}

test('the device detail renders with its customer and ticket history', function () {
    $device = deviceWithTicket($this->business->id);

    // Regression: $appends = ['device_progress'] had no matching accessor, so
    // serializing the device (toArray inside Inertia) threw
    // Call to undefined method Device::getDeviceProgressAttribute().
    expect($device->toArray())->toHaveKey('device_progress');

    $this->actingAs($this->user)
        ->get(route('devices.show', $device))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Devices/Show')
            ->where('device.id', $device->id)
            ->where('device.customer.name', 'Dana Owner')
            ->where('tickets.0.title', 'Cracked screen'));
});

test('the device detail renders for a device with no tickets yet', function () {
    $customer = Customer::factory()->create(['business_id' => $this->business->id]);
    $device = Device::factory()->forCustomer($customer)->create(['business_id' => $this->business->id]);

    $this->actingAs($this->user)
        ->get(route('devices.show', $device))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Devices/Show')
            ->where('tickets', fn ($tickets) => $tickets->count() === 0));
});
