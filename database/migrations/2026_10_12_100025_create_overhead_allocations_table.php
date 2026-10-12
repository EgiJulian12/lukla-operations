<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alokasi overhead per unit produk per bulan (FR-018).
 *
 * overhead_per_unit = total_overhead bulan tersebut / total seluruh unit
 * produk yang diproduksi di bulan itu. Hasil perhitungan disimpan (bukan
 * dihitung ulang saat tampil) agar laporan bulan lalu tetap konsisten
 * meski data produksi sesudahnya berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overhead_allocations', function (Blueprint $table) {
            $table->id();
            $table->date('period_month')->unique();
            $table->decimal('total_overhead', 16, 2)->default(0);
            $table->unsignedBigInteger('total_units')->default(0);
            $table->decimal('overhead_per_unit', 16, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overhead_allocations');
    }
};
