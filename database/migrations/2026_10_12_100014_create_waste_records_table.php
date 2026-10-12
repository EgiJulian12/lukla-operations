<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan sisa potongan bahan / scrap (FR-008). Terikat klien dan pesanan
 * agar efisiensi bahan dapat dilaporkan per klien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->string('uom', 20)->default('meter');
            $table->string('category')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_records');
    }
};
