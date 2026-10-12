<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya overhead operasional pabrik bulanan (FR-018): listrik, air, internet,
 * dan biaya operasional lain yang disetujui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overhead_records', function (Blueprint $table) {
            $table->id();
            $table->date('period_month');
            $table->string('category');
            $table->decimal('amount', 16, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['period_month', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overhead_records');
    }
};
