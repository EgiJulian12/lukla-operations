<?php

use App\Enums\OutputStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Output penjahit per interval 30 menit (FR-011, FR-012).
 *
 * Output terkait klien dan pesanan agar dapat diatribusikan ke payroll dan QC
 * yang benar. Kolom verifikasi merekam siapa mandor yang memverifikasi dan
 * kapan — output terverifikasi terkunci dan tidak dapat diedit penjahit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('output_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_line_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('component_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('interval_start');
            $table->timestamp('interval_end');
            $table->string('status')->default(OutputStatus::SUBMITTED->value);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'order_id']);
            $table->index(['user_id', 'interval_start']);
            $table->index(['status', 'interval_start']);
            // Satu penjahit hanya boleh punya satu catatan per interval per komponen.
            $table->unique(['user_id', 'component_id', 'interval_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('output_entries');
    }
};
