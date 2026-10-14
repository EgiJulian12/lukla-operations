<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan penjahit ke komponen sebuah order (FR-009).
 *
 * Menggantikan alokasi berbasis line sebagai penentu siapa mengerjakan apa.
 * `line_assignments` menautkan penjahit ke line, dan satu line melayani order
 * lintas klien, sehingga keanggotaan line tidak pernah menunjukkan komponen
 * mana yang menjadi tanggung jawab seorang penjahit. Baris di sini mengikat
 * pasangan (order, komponen) ke tepat satu penjahit.
 *
 * Rencana ditetapkan sekali saat Kepala Produksi memulai produksi, bersamaan
 * dengan perpindahan status order — lihat ProductionService::startProduction().
 * Karena itu tabel ini tidak mengenal penugasan yang berakhir (`unassigned_at`):
 * seluruh komponen artikel sudah tercakup sejak order berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_component_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();

            // Satu komponen dipegang satu penjahit; seorang penjahit boleh
            // memegang beberapa komponen pada order yang sama.
            $table->unique(['order_id', 'component_id']);
            $table->index(['user_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_component_assignments');
    }
};
