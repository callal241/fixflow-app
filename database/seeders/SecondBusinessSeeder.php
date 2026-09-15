<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Populate the secondary demo business so multi-tenant scoping can be
 * demonstrated in the app, not only asserted in tests.
 *
 * The business row itself is created by BusinessSeeder; this seeder gives it
 * a staff login plus a small, self-contained dataset (customers, devices,
 * tickets, a category, and a product). Every row is stamped with the second
 * business id so it is fully isolated from the demo shop.
 */
class SecondBusinessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $business = Business::where('name', BusinessSeeder::SECOND_BUSINESS_NAME)->first();

        if ($business === null) {
            return;
        }

        $businessId = $business->id;

        // A staff member for the second shop (password: "password").
        $manager = User::factory()->manager()->create([
            'email' => 'manager@secondbird.test',
            'business_id' => $businessId,
            'created_at' => now()->subMonths(3),
        ]);

        // A couple of customers.
        $customers = Customer::factory(3)->create([
            'business_id' => $businessId,
        ]);

        // Each customer gets a device; each device gets a ticket.
        $customers->each(function (Customer $customer) use ($manager, $businessId) {
            $device = Device::factory()->forCustomer($customer)->create([
                'business_id' => $businessId,
            ]);

            Ticket::factory()
                ->forDevice($device)
                ->forAssignee($manager)
                ->create([
                    'business_id' => $businessId,
                ]);
        });

        // A small catalog so the second shop can sell from its own stock.
        $category = Category::factory()->create([
            'business_id' => $businessId,
            'name' => 'General',
        ]);

        Product::factory(4)
            ->inCategory($category)
            ->create([
                'business_id' => $businessId,
            ]);
    }
}
