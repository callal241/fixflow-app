<?php

namespace Database\Seeders;

use App\Enums\AdjustmentReason;
use App\Models\Adjustment;
use App\Models\Invoice;
use Illuminate\Database\Seeder;

class AdjustmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create adjustments for pending invoices (adjustments inherit the invoice's business)
        Invoice::pending()->each(function (Invoice $invoice) {

            // Create an adjustment factory for the invoice
            $adjustmentFactory = Adjustment::factory()->forInvoice($invoice);
            $businessId = $invoice->business_id;

            // Create a percentage based adjustment with various states
            match (rand(1, 6)) {
                // bonus
                1 => $adjustmentFactory->ofReason(AdjustmentReason::Welcome)->create(['business_id' => $businessId]),
                // discount
                2 => $adjustmentFactory->ofReason(AdjustmentReason::Promotion)->create(['business_id' => $businessId]),
                // fee
                3 => $adjustmentFactory->ofReason(AdjustmentReason::RushService)->create(['business_id' => $businessId]),
                // compensation
                4 => $adjustmentFactory->ofReason(AdjustmentReason::ServiceDelay)->create(['business_id' => $businessId]),
                // fixed amount bonus
                5 => $adjustmentFactory->ofReason(AdjustmentReason::Loyalty)->withAmount(10)->create(['business_id' => $businessId]),
                // fixed amount fee
                6 => $adjustmentFactory->ofReason(AdjustmentReason::Service)->withAmount(50)->create(['business_id' => $businessId]),
            };
        });
    }
}
