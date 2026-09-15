<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wire catalog products into the repair/sale flow.
 *
 * An Order is already a billable line on a ticket (its `cost` is the billed
 * line total that `Invoice::fillOrderTotal()` sums). This migration lets an
 * Order optionally point at a catalog Product, so a shop can sell a stocked
 * item against a ticket and have stock + totals flow automatically:
 *
 *  - `product_id`  – which catalog product this line is (nullable, so free-form
 *                    "part" orders that aren't in the catalog still work).
 *  - `price`       – the unit sale price at the time of the sale (snapshot from
 *                    product.price). Kept separate from `cost` (the billed line
 *                    total = price x quantity) so margin can be computed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('product_id')
                ->nullable()
                ->after('name')
                ->constrained()
                ->nullOnDelete();
            $table->decimal('price', 12, 2)->default(0)->after('quantity');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn('price');
        });
    }
};
