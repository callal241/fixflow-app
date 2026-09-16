<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('ticket_number')->nullable()->unique();
            $table->text('internal_notes')->nullable();
            $table->string('intake_type')->default('walk_in');
            // A ticket may carry only a title; the description is optional at intake.
            $table->text('description')->nullable()->change();
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->string('model_number')->nullable();
            $table->string('imei')->nullable();
            $table->string('color')->nullable();
            $table->string('storage')->nullable();
            $table->string('carrier')->nullable();
        });

        // Backfill ticket numbers for rows created before this column existed.
        DB::statement("UPDATE tickets SET ticket_number = 'FF-' || printf('%05d', id) WHERE ticket_number IS NULL");
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['ticket_number']);
            $table->dropColumn(['ticket_number', 'internal_notes', 'intake_type']);
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['model_number', 'imei', 'color', 'storage', 'carrier']);
        });
    }
};
