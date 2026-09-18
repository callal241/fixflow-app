<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parts, purchase orders, and reserved stock (Milestone 2 item 4).
 *
 * An Order is already the ticket's billable part/purchase line. This migration
 * adds what receiving and stock reservation need:
 *
 *  - products.reserved_stock -- quantity allocated to open tickets (on hold,
 *    not yet consumed). available = stock - reserved_stock.
 *  - orders.is_purchase -- true when the line is a purchase to be received from
 *    a supplier (receiving restocks a linked catalog product). false = a part
 *    pulled from the shop's own shelf (receiving consumes the reservation).
 *  - orders.received_at -- when the line was received.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('reserved_stock')->default(0)->after('stock');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('is_purchase')->default(false)->after('status');
            $table->timestamp('received_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('reserved_stock');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['is_purchase', 'received_at']);
        });
    }
};
