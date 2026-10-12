<?php

use App\Enums\SampleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi sample dengan versioning v1, v2, dst (FR-001, BR-005).
 *
 * Setiap revisi menyimpan spesifikasi dan pemakaian bahan sebagai JSON agar
 * riwayat tetap dapat direproduksi meski skema spesifikasi berubah. Revisi
 * yang disetujui direkam beserta penyetuju dan waktunya (FR-026).
 *
 * client_id sengaja didenormalisasi (diturunkan dari sample induk) supaya
 * global scope isolasi klien dapat diterapkan langsung pada tabel ini — bukan
 * hanya lewat relasi. Foreign key komposit ke samples(id, client_id) membuat
 * PostgreSQL menolak revisi yang client_id-nya tidak cocok dengan sample-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sample_id');
            $table->unsignedInteger('version');
            $table->json('specs')->nullable();
            $table->json('material_usage')->nullable();
            $table->string('status')->default(SampleStatus::DRAFT->value);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['sample_id', 'version']);

            // Menjaga konsistensi client_id terhadap sample induk, di level DB.
            $table->foreign(['sample_id', 'client_id'])
                ->references(['id', 'client_id'])
                ->on('samples')
                ->cascadeOnDelete();

            // Sasaran FK komposit dari time_studies.
            $table->unique(['id', 'client_id']);

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_revisions');
    }
};
