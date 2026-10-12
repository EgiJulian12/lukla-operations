<?php

use App\Enums\InventoryState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item stok di gudang (FR-005). Setiap item — bahan mentah, WIP, maupun
 * barang jadi — WAJIB terikat ke client_id dan order_id. Tidak ada item yatim.
 *
 * unique(['id', 'client_id', 'order_id']) bukan sekadar index: kunci ini
 * dipakai sebagai sasaran foreign key komposit dari inventory_movements,
 * sehingga kecocokan klien/pesanan ditegakkan oleh PostgreSQL sendiri
 * (lihat migration inventory_movements).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('batch')->nullable();
            $table->string('state')->default(InventoryState::RAW->value);
            $table->decimal('quantity', 14, 2)->default(0);
            $table->string('uom', 20)->default('meter');
            $table->string('location')->nullable();
            $table->timestamps();

            // Sasaran FK komposit — menegakkan isolasi multi-tenant di level DB.
            $table->unique(['id', 'client_id', 'order_id']);

            $table->index(['client_id', 'state']);
            $table->index(['order_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
