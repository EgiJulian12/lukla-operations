<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pool bonus QC (FR-017).
 *
 * Pool dibentuk dari SELURUH unit yang lolos QC lintas order dan lintas klien,
 * dikalikan rate bonus per pcs. Total pool kemudian dibagi RATA kepada tiga
 * penerima: 2 Mandor dan 1 Kepala Operasional.
 *
 * recipient_count disimpan eksplisit (default 3) agar pembagian dapat diaudit
 * tanpa menebak kebijakan yang berlaku saat perhitungan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_bonus_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_passed_units')->default(0);
            $table->decimal('rate_per_unit', 12, 2)->default(0);
            $table->decimal('total_pool', 16, 2)->default(0);
            $table->unsignedInteger('recipient_count')->default(3);
            $table->decimal('amount_per_recipient', 16, 2)->default(0);
            $table->timestamps();

            $table->index('payroll_period_id');
        });

        // Rincian pembagian pool ke masing-masing penerima.
        Schema::create('qc_bonus_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_bonus_pool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 16, 2);
            $table->timestamps();

            $table->unique(['qc_bonus_pool_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_bonus_distributions');
        Schema::dropIfExists('qc_bonus_pools');
    }
};
