<?php

use App\Enums\MovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pergerakan stok (FR-006, FR-007).
 *
 * ATURAN ANTI-KONTAMINASI SILANG — ditegakkan oleh database, bukan hanya kode:
 *
 *   foreign(['inventory_item_id', 'client_id', 'order_id'])
 *       ->references(['id', 'client_id', 'order_id'])->on('inventory_items')
 *
 * Foreign key komposit ini membuat PostgreSQL MENOLAK setiap pergerakan yang
 * mencatat client_id/order_id berbeda dari item stok yang dipindahkan — bahkan
 * jika validasi aplikasi dilewati (bug, seeder, atau perintah artisan langsung).
 * Inilah lapisan ketiga pertahanan di samping global scope dan policy.
 *
 * Validasi aplikasi tetap diperlukan di atasnya agar pengguna menerima pesan
 * peringatan eksplisit sebelum transaksi dicoba (NFR-002).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('inventory_item_id');
            $table->string('movement_type')->default(MovementType::RECEIPT->value);
            $table->decimal('quantity', 14, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            // Penjaga isolasi klien/pesanan di level database.
            $table->foreign(['inventory_item_id', 'client_id', 'order_id'])
                ->references(['id', 'client_id', 'order_id'])
                ->on('inventory_items')
                ->restrictOnDelete();

            $table->index(['client_id', 'recorded_at']);
            $table->index('movement_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
