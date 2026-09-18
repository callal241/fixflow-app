<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Which parts supplier the shop uses. NULL means "use the
            // configured default". The id must map to an entry in
            // config('suppliers.providers'); the manual provider is always
            // available as a fallback.
            $table->string('supplier_provider_id')->nullable()->after('payment_provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('supplier_provider_id');
        });
    }
};
