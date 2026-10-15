<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menautkan akun Telegram ke user (binding 1:1).
 * chat_id dipakai untuk kirim notifikasi, user_id untuk identitas Telegram.
 * Linking dilakukan lewat token sekali-pakai di telegram_link_tokens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->unique()->after('is_active');
            $table->string('telegram_user_id')->nullable()->unique()->after('telegram_chat_id');
            $table->timestamp('telegram_linked_at')->nullable()->after('telegram_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_user_id', 'telegram_linked_at']);
        });
    }
};
