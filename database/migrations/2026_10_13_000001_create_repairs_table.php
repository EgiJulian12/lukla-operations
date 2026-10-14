<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel repairs untuk alur kerja unit cacat hasil QC (FR-014, BR 4.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_record_id')->constrained('qc_records')->cascadeOnDelete();
            $table->foreignId('assigned_repairer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['qc_record_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repairs');
    }
};
