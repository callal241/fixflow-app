<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Ticket;
use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create invoices for each ticket (invoices inherit the ticket's business)
        Ticket::all()->each(function ($ticket) {
            // Create an invoice factory for the ticket
            $invoiceFactory = Invoice::factory()->forTicket($ticket);
            $businessId = $ticket->business_id;

            // Create an invoice with various states
            match (rand(1, 4)) {
                1 => $invoiceFactory->create(['business_id' => $businessId]),
                2 => $invoiceFactory->paid()->create(['business_id' => $businessId]),
                3 => $invoiceFactory->refunded()->create(['business_id' => $businessId]),
                4 => $invoiceFactory->overdue()->create(['business_id' => $businessId]),
            };
        });
    }
}
