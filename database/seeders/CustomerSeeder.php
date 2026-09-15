<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $businessId = Business::where('name', BusinessSeeder::DEMO_BUSINESS_NAME)
            ->value('id');

        User::all()->each(function (User $user) use ($businessId) {
            // Create a customer for the user
            $startDate = fn () => fake()->dateTimeBetween($user->created_at);

            // Create a default customer
            Customer::factory()->create([
                'business_id' => $businessId,
                'created_at' => $startDate,
            ]);

            // Create a customer without company
            Customer::factory()->withoutCompany()->create([
                'business_id' => $businessId,
                'created_at' => $startDate,
            ]);

            // Create a customer without email
            Customer::factory()->notMailable()->create([
                'business_id' => $businessId,
                'created_at' => $startDate,
            ]);

            // Create a customer without phone
            Customer::factory()->notCallable()->create([
                'business_id' => $businessId,
                'created_at' => $startDate,
            ]);

            // Create a customer without address
            Customer::factory()->withoutAddress()->create([
                'business_id' => $businessId,
                'created_at' => $startDate,
            ]);

            // Create a customer without note
            Customer::factory()->withoutNote()->create([
                'business_id' => $businessId,
                'created_at' => $startDate,
            ]);
        });
    }
}
