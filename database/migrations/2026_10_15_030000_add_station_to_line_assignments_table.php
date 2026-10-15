<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan nomor/nama stasiun kerja pada alokasi line penjahit (FR-009, Task 4.1).
 * Memungkinkan penjahit ditempatkan pada stasiun spesifik dan dirotasi secara dinamis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('line_assignments', function (Blueprint $table) {
            $table->string('station_number')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('line_assignments', function (Blueprint $table) {
            $table->dropColumn('station_number');
        });
    }
};
