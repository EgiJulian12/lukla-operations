<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time study per komponen pada sebuah revisi sample (FR-002).
 *
 * Durasi disimpan dalam detik agar konsisten dengan stopwatch digital, lalu
 * ditampilkan kembali dalam format menit:detik di UI.
 *
 * Sama seperti sample_revisions, client_id didenormalisasi agar global scope
 * isolasi klien berlaku langsung, dan dijaga konsisten oleh FK komposit ke
 * sample_revisions(id, client_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sample_revision_id');
            $table->foreignId('component_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('duration_seconds');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign(['sample_revision_id', 'client_id'])
                ->references(['id', 'client_id'])
                ->on('sample_revisions')
                ->cascadeOnDelete();

            $table->index(['sample_revision_id', 'component_id']);
            $table->index(['client_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_studies');
    }
};
