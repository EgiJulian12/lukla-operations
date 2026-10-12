<?php

use App\Enums\SampleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sample = prototipe sebelum produksi massal (FR-001).
 *
 * Kolom current_revision_id dan approved_revision_id diisi belakangan melalui
 * migrasi terpisah, karena sample_revisions belum ada saat tabel ini dibuat
 * (relasi melingkar samples <-> sample_revisions).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->string('status')->default(SampleStatus::DRAFT->value);
            $table->unsignedBigInteger('current_revision_id')->nullable();
            $table->unsignedBigInteger('approved_revision_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['client_id', 'code']);
            // Sasaran FK komposit dari sample_revisions — menjaga client_id
            // revisi tetap sama dengan client_id sample induknya.
            $table->unique(['id', 'client_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('samples');
    }
};
