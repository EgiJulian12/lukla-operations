<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komponen = bagian dari artikel yang dijahit terpisah lalu dirakit
 * (mis. badan depan, lengan, kerah). Time study dan output 30 menit
 * dicatat per komponen (FR-002, FR-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamps();

            $table->unique(['article_id', 'code']);
            $table->index('sequence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('components');
    }
};
