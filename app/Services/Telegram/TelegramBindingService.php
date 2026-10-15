<?php

namespace App\Services\Telegram;

use App\Models\TelegramLinkToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menautkan akun Telegram ke User (binding 1:1, sekali-pakai via token).
 *
 * Alur: user menekan "Hubungkan Telegram" di panel Filament, menerima deep
 * link dengan token acak (disimpan sebagai SHA-256), lalu mengirim /start
 * dari bot. Telegram hanya membuktikan identitas chat, jadi kesatuan bot
 * (client vs owner) dicocokkan dengan jenis akun yang menautkan.
 */
class TelegramBindingService
{
    public function __construct(private readonly TelegramBotConfig $config) {}

    /**
     * Bot yang seharusnya dipakai user: klien maklon lewat bot client,
     * staf internal lewat bot owner.
     */
    public function botNameFor(User $user): string
    {
        return $user->isClientUser() ? 'client' : 'owner';
    }

    /**
     * Membuat token sekali-pakai lalu mengembalikan deep link siap-klik.
     * Token di-hash sebelum disimpan; nilai mentah tidak dipersistenkan.
     */
    public function startUrlFor(User $user): string
    {
        $bot = $this->botNameFor($user);
        $raw = Str::random(32);

        TelegramLinkToken::query()->create([
            'user_id' => $user->getKey(),
            'token' => hash('sha256', $raw),
            'expires_at' => now()->addMinutes(15),
        ]);

        return $this->config->startUrl($bot, $raw);
    }

    /**
     * Menautkan chat Telegram ke user lewat token mentah. Mengembalikan null
     * bila token tidak dikenal, kedaluwarsa, sudah dipakai, atau token dipakai
     * di bot yang salah (token klien tidak boleh me-link via bot owner).
     * Token konsumsinya satu kali (used_at) supaya replay tidak valid.
     */
    public function bindByToken(string $rawToken, int $telegramUserId, int $chatId, ?string $botName = null): ?User
    {
        $record = TelegramLinkToken::query()
            ->where('token', hash('sha256', $rawToken))
            ->valid()
            ->first();

        if ($record === null) {
            return null;
        }

        if ($botName !== null && $this->botNameFor($record->user) !== $botName) {
            return null;
        }

        $user = $record->user;

        // Transaksi menyatukan pemakaian token dengan penyimpanan binding:
        // bila penyimpanan gagal (mis. chat kunci unik), token tidak hangus,
        // sehingga user dapat mencoba ulang dengan tautan yang sama.
        return DB::transaction(function () use ($record, $user, $telegramUserId, $chatId): User {
            TelegramLinkToken::query()
                ->whereKey($record->getKey())
                ->update(['used_at' => now()]);

            $user->forceFill([
                'telegram_chat_id' => (string) $chatId,
                'telegram_user_id' => (string) $telegramUserId,
                'telegram_linked_at' => now(),
            ])->save();

            return $user;
        });
    }

    public function findUserByChatId(int $chatId): ?User
    {
        return User::query()->where('telegram_chat_id', (string) $chatId)->first();
    }

    public function findUserByTelegramId(int $telegramUserId): ?User
    {
        return User::query()->where('telegram_user_id', (string) $telegramUserId)->first();
    }
}
