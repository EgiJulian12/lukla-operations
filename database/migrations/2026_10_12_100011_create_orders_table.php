<?php

use App\Enums\OrderStatus;
use App\Enums\WorkScheme;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order produksi massal (FR-004). Order membawa revisi sample yang disetujui
 * dan skema maklon yang dipilih, sehingga riwayat spesifikasi tetap menempel
 * pada order (BR-005).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sample_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sample_revision_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('work_scheme')->default(WorkScheme::CMT->value);
            $table->unsignedInteger('quantity');
            $table->string('status')->default(OrderStatus::DRAFT->value);
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['client_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
