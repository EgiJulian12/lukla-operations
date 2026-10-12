<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perakitan komponen menjadi produk utuh (FR-013). Setelah dirakit, produk
 * berpindah ke status siap QC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assemblies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->foreignId('assembled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assembled_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'order_id']);
        });

        // Output terverifikasi yang menyusun sebuah perakitan (many-to-many).
        Schema::create('assembly_output_entry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assembly_id')->constrained()->cascadeOnDelete();
            $table->foreignId('output_entry_id')->constrained()->cascadeOnDelete();

            $table->unique(['assembly_id', 'output_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assembly_output_entry');
        Schema::dropIfExists('assemblies');
    }
};
