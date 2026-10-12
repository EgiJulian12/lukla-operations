<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak audit (FR-022, NFR-005, NFR-006).
 *
 * Tabel ini HANYA ditambah, tidak pernah diubah atau dihapus — model AuditLog
 * menolak update dan delete. Setiap baris menyimpan payload_hash yang dirantai
 * ke previous_hash baris sebelumnya, sehingga penghapusan atau penyuntingan
 * satu baris di tengah rantai dapat dideteksi: hash baris sesudahnya tidak lagi
 * cocok.
 *
 * client_id dan order_id disimpan apa adanya (bukan hanya lewat relasi) agar
 * riwayat tetap terbaca meski entitas asalnya kelak dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('auditable');
            $table->string('event');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->string('payload_hash', 64);
            $table->string('previous_hash', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['client_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
