<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upah borongan per penjahit per periode (FR-015).
 *
 * Tidak ada batasan jumlah penjahit yang dapat diproses — penjahit yang baru
 * bergabung di tengah minggu tetap mendapat baris sendiri (NFR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('total_pieces')->default(0);
            $table->decimal('sewing_amount', 16, 2)->default(0);
            $table->decimal('meal_allowance_amount', 16, 2)->default(0);
            $table->decimal('total_amount', 16, 2)->default(0);
            $table->timestamps();

            $table->unique(['payroll_period_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_entries');
    }
};
