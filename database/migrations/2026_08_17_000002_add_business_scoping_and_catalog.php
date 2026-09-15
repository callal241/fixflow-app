<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The core business tables that become business-scoped.
     *
     * Kept as a single source of truth so the migration and any future
     * re-scoping logic stay in sync. Adding a table here is all that is
     * required to bring it under business ownership.
     */
    private const SCOPED_TABLES = [
        'customers',
        'devices',
        'tickets',
        'tasks',
        'orders',
        'invoices',
        'transactions',
        'adjustments',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Bring the core tables under business ownership.
        //    Nullable on purpose: existing / unowned rows stay valid, and a
        //    "shared" record (no business) remains a possible, easy-to-change
        //    state. Read-scoping is applied explicitly in queries.
        foreach (self::SCOPED_TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('business_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->nullOnDelete();
                $t->index('business_id');
            });
        }

        // 2. Catalog: a flexible, recursive category tree per business.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'parent_id']);
        });

        // 3. Catalog: the sellable goods (new, used, or "odd" items).
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('description')->nullable();
            // Open-ended (not an enum) so any item — a toaster, a radio, a
            // vintage console — can be classified without a schema change.
            $table->string('condition')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost', 12, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->integer('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'is_active']);
            $table->unique(['business_id', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');

        foreach (array_reverse(self::SCOPED_TABLES) as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('business_id');
            });
        }
    }
};
