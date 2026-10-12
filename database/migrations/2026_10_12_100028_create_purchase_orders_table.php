<?php

use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PO bahan (matriks RBAC SRS, Finance: buat/setujui PO bahan).
 *
 * Pemasok berupa teks bebas; BRD mengeluarkan manajemen pemasok dari lingkup,
 * jadi tidak ada tabel pemasok yang perlu dipelihara.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->string('supplier');
            $table->string('status')->default(PurchaseOrderStatus::DRAFT->value);
            $table->date('ordered_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
