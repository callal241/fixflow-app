<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // When the customer authorized the estimate/invoice. NULL means
            // the shop has not yet received approval to proceed. This is an
            // authorization marker, independent of the money status: an
            // approved invoice that is still unpaid surfaces as "Sent".
            $table->timestamp('approved_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });
    }
};
