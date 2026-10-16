<?php

use App\Enums\DispatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelacakan pengantaran bahan dari gudang ke bagian produksi / cutting (FR-012, Track D).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_number')->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->string('uom', 20)->default('meter');
            $table->string('status')->default(DispatchStatus::REQUESTED->value);
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cutting_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Penjaga isolasi multi-tenant di level basis data
            $table->foreign(['inventory_item_id', 'client_id', 'order_id'])
                ->references(['id', 'client_id', 'order_id'])
                ->on('inventory_items')
                ->restrictOnDelete();

            $table->index(['client_id', 'order_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_dispatches');
    }
};
