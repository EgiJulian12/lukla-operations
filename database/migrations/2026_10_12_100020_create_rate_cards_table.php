<?php

use App\Enums\RateType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rate card ongkos potong/jahit per artikel, komponen, dan klien (FR-010).
 *
 * Berlaku efektif dengan rentang tanggal, bukan ditimpa, sehingga riwayat
 * perubahan tarif tetap tersimpan dan perhitungan payroll periode lama dapat
 * direproduksi dengan tarif yang benar saat itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('component_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('rate_type')->default(RateType::SEWING->value);
            $table->decimal('rate', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'rate_type', 'effective_from']);
            $table->index(['component_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_cards');
    }
};
