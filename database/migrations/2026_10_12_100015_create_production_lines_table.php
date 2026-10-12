<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line produksi paralel (FR-009). Tidak ada batas jumlah slot kaku —
 * penjahit dialokasikan secara dinamis hingga kapasitas ±70 mesin (NFR-001).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_lines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_lines');
    }
};
