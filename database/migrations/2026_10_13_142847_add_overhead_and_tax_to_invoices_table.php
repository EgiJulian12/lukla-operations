<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahkan kolom overhead dan pajak ke tabel invoices.
 *
 * - overhead_amount : total biaya overhead (0 untuk skema CMT murni)
 * - tax_percent     : persentase PPN yang diinput Finance (nullable = belum diisi)
 * - tax_amount      : nilai PPN = (sewing + overhead) × tax_percent / 100
 *
 * Ketiga kolom nullable agar baris invoice lama tidak terpengaruh.
 * `amount` di kolom sebelumnya tetap sebagai GRAND TOTAL
 * (sewing + overhead + tax) — sumber kebenaran tunggal untuk tagihan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Biaya overhead dialokasikan per unit × qty order
            $table->decimal('overhead_amount', 16, 2)
                ->nullable()
                ->default(null)
                ->after('amount')
                ->comment('Overhead per unit × qty order; 0 jika skema CMT');

            // PPN diinput Finance, nullable = belum ada pajak
            $table->decimal('tax_percent', 8, 4)
                ->nullable()
                ->default(null)
                ->after('overhead_amount')
                ->comment('Persentase PPN, mis. 11.0000 untuk 11%');

            // Nilai PPN dihitung otomatis = (sewing + overhead) × tax_percent / 100
            $table->decimal('tax_amount', 16, 2)
                ->nullable()
                ->default(null)
                ->after('tax_percent')
                ->comment('Nilai PPN = (sewing + overhead) × tax_percent / 100');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['overhead_amount', 'tax_percent', 'tax_amount']);
        });
    }
};
