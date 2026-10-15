<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token sekali-pakai untuk /start di bot: user di portal generate,
 * lalu dipakai Telegram untuk membuktikan pemilik akun.
 * Token disimpan sebagai hash; verdict kedaluwarsa 15 menit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_link_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_link_tokens');
    }
};
