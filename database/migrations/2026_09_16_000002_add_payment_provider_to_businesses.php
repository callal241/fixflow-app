<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            // Which payment provider the shop uses. NULL means "use the
            // configured default". The id must map to an entry in
            // config('payments.providers'); the counter provider is always
            // available as a fallback.
            $table->string('payment_provider_id')->nullable()->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('payment_provider_id');
        });
    }
};
