<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line pada output 30 menit menjadi opsional (FR-009, FR-011).
 *
 * Penugasan penjahit kini ditetapkan per komponen, bukan lewat keanggotaan
 * line, sehingga penjahit tidak lagi harus terdaftar di sebuah line sebelum
 * hasil kerjanya boleh dicatat. Line tetap diisi bila penjahit sedang bertugas
 * di satu line — berguna untuk menelusuri output per line — tetapi
 * ketiadaannya tidak lagi menghalangi pelaporan, dan tidak menghalangi payroll,
 * yang dihitung dari kuantitas output terverifikasi dikali tarif komponen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('output_entries', function (Blueprint $table) {
            // Definisi kolom harus lengkap: change() mengganti seluruh definisi,
            // sehingga atribut yang tidak disebut akan ikut hilang. Foreign key
            // dan indeksnya tidak disentuh karena keduanya terpisah dari kolom.
            $table->foreignId('production_line_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('output_entries', function (Blueprint $table) {
            $table->foreignId('production_line_id')->nullable(false)->change();
        });
    }
};
