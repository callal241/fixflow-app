<?php

namespace Database\Seeders;

use App\Models\Business;
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    /**
     * The display name of the primary demo business.
     *
     * Shared so every other seeder can attach its data to the same shop.
     */
    public const DEMO_BUSINESS_NAME = 'FixFlow Demo Shop';

    /**
     * The display name of the secondary demo business.
     *
     * A distinct tenant, used to prove (and demonstrate) business scoping.
     */
    public const SECOND_BUSINESS_NAME = 'Second Bird Repair Co.';

    /**
     * Seed the businesses table.
     *
     * Creates the demo shop (used for the default demo staff) plus a second
     * shop so business-scoping / multi-tenant behaviour can be verified.
     */
    public function run(): void
    {
        // The primary demo business. Demo staff are attached to this one.
        Business::create([
            'name' => self::DEMO_BUSINESS_NAME,
            'trade' => 'Electronics Repair & Resale',
            'email' => 'hello@fixflowdemo.test',
            'phone' => '+1 555 0100',
            'website' => 'https://fixflowdemo.test',
            'currency' => 'USD',
            'tax_rate' => 8.25,
            'created_at' => now()->subMonths(6),
        ]);

        // A second business to prove that data is scoped per-shop.
        Business::create([
            'name' => self::SECOND_BUSINESS_NAME,
            'trade' => 'Avionics & General Electronics',
            'email' => 'office@secondbird.test',
            'phone' => '+1 555 0200',
            'currency' => 'USD',
            'tax_rate' => 7.0,
            'created_at' => now()->subMonths(3),
        ]);
    }
}
