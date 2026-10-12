<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bahan baku. client_id NULL berarti bahan generik/pasaran yang dipakai
 * lintas klien; bahan milik klien titipan disimpan dengan client_id terisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('uom', 20)->default('meter');
            $table->string('category')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('client_id');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
