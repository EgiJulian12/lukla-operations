<?php

use App\Enums\QcResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil inspeksi QC (FR-014). Hanya unit LOLOS yang membentuk pool bonus QC
 * lintas order dan lintas klien (FR-017), sehingga client_id dan order_id
 * tetap direkam untuk rekonsiliasi pool.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qc_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('assembly_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result')->default(QcResult::PASS->value);
            $table->foreignId('defect_category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'order_id']);
            $table->index(['result', 'inspected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_records');
    }
};
