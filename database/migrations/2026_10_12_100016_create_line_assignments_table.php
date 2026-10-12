<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan penjahit ke line produksi (FR-009). Tanpa batas jumlah slot:
 * kapasitas bersifat cair mengikuti volume order (NFR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'unassigned_at']);
            $table->index(['production_line_id', 'unassigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_assignments');
    }
};
