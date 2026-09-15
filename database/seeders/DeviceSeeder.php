<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Device;
use Illuminate\Database\Seeder;

class DeviceSeeder extends Seeder
{
    /**
     * Seed the devices table.
     */
    public function run(): void
    {
        Customer::all()->each(function ($customer) {
            // Create a device factory for the customer
            $deviceFactory = Device::factory()->forCustomer($customer);
            // Devices inherit their owner's business so data stays shop-scoped.
            $businessId = $customer->business_id;

            // Create a device with various states
            match (rand(1, 5)) {
                1 => $deviceFactory->create(['business_id' => $businessId]),
                2 => $deviceFactory->withoutBrand()->create(['business_id' => $businessId]),
                3 => $deviceFactory->withoutSerialNumber()->create(['business_id' => $businessId]),
                4 => $deviceFactory->withoutPurchaseDate()->create(['business_id' => $businessId]),
                5 => $deviceFactory->withoutWarranty()->create(['business_id' => $businessId]),
            };
        });
    }
}
