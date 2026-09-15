<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Demo staff belong to the primary demo business.
        $businessId = Business::where('name', BusinessSeeder::DEMO_BUSINESS_NAME)
            ->value('id');

        // Create a default admin user (earliest)
        User::factory()->admin()->create([
            'email' => 'admin@demo.com',
            'business_id' => $businessId,
            'created_at' => now()->subMonths(6),
        ]);

        // Create a default manager user (after admin)
        User::factory()->manager()->create([
            'email' => 'manager@demo.com',
            'business_id' => $businessId,
            'created_at' => now()->subMonths(5),
        ]);

        // Create a default technician user (after manager)
        User::factory()->create([
            'email' => 'technician@demo.com',
            'business_id' => $businessId,
            'created_at' => now()->subMonths(4),
        ]);

        // Create additional random users for each status (recently)
        UserStatus::all()->each(function (UserStatus $status) use ($businessId) {
            User::factory(5)->ofStatus($status)->create([
                'business_id' => $businessId,
                'created_at' => now()->subMonths(rand(1, 3)),
            ]);
        });
    }
}
