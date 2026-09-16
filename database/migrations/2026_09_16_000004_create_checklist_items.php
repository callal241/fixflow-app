<?php

use App\Enums\ChecklistPhase;
use App\Enums\ChecklistStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('note')->nullable();
            $table->enum('phase', ChecklistPhase::values())->default(ChecklistPhase::PreRepair);
            $table->enum('status', ChecklistStatus::values())->default(ChecklistStatus::Pending);
            $table->foreignId('checked_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->index('business_id');
            $table->index(['ticket_id', 'phase']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
    }
};
