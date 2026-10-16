<?php

namespace App\Services\Ai;

use App\Enums\UserRole;
use App\Models\TelegramRegistrationToken;
use App\Models\User;
use App\Services\Telegram\TelegramBotConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Manages the token-based Telegram self-registration flow.
 *
 * Token lifecycle:
 *  - Admin generates a token via Filament → stored in telegram_registration_tokens.
 *  - Client sends /start <token> to the bot.
 *  - This service validates, consumes, and links — all in one DB transaction.
 *
 * Rules:
 *  - Tokens expire after 24 hours.
 *  - A used token can never be reused (used_at prevents it).
 *  - A chat_id already linked to another user is rejected (answer 3=b).
 *  - Only CLIENT_MAKLON users with a non-null client_id may register.
 */
class TelegramRegistrationService
{
    private const TOKEN_TTL_HOURS = 24;

    public function __construct(
        private readonly TelegramBotConfig $botConfig,
    ) {}

    /**
     * Generate a fresh registration token for the given user.
     *
     * Any previously unused tokens for this user are invalidated first so
     * there is never more than one valid token per user.
     *
     * @return array{token: string, expires_at: Carbon}
     */
    public function generateToken(User $user): array
    {
        // Invalidate prior unused tokens by setting expires_at to now.
        TelegramRegistrationToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['expires_at' => now()]);

        $token = Str::upper(Str::random(12));

        $record = TelegramRegistrationToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'expires_at' => now()->addHours(self::TOKEN_TTL_HOURS),
        ]);

        Log::info('TelegramRegistrationService: token generated.', [
            'user_id' => $user->id,
            'expires_at' => $record->expires_at,
        ]);

        return [
            'token' => $token,
            'expires_at' => $record->expires_at,
        ];
    }

    /**
     * Build a one-tap Telegram deep link that registers the user automatically.
     *
     * Opening the link starts a chat with the bot and sends `/start <token>` on
     * the user's behalf, so the client never has to type the command — the link
     * flows through the normal webhook registration path. Share it as-is; it is
     * as sensitive as the token itself.
     */
    public function buildDeepLink(string $token): string
    {
        return $this->botConfig->startUrl('client', strtoupper(trim($token)));
    }

    /**
     * Attempt to register a Telegram chat_id using a token from /start <token>.
     *
     * @return array{success: bool, message: string, user?: User}
     */
    public function register(string $token, string|int $chatId): array
    {
        $chatId = (string) $chatId;
        $token = strtoupper(trim($token));

        // --- Find and validate the token ---
        $record = TelegramRegistrationToken::query()
            ->with('user')
            ->valid()
            ->where('token', $token)
            ->first();

        if ($record === null) {
            return [
                'success' => false,
                'message' => 'Token tidak valid atau sudah kedaluwarsa. Minta admin untuk membuat token baru.',
            ];
        }

        $user = $record->user;

        // --- Validate the user ---
        if (! $user->is_active) {
            return [
                'success' => false,
                'message' => 'Akun Anda tidak aktif. Hubungi admin Lukla Operations.',
            ];
        }

        if (! $user->hasRole(UserRole::CLIENT_MAKLON->value) || $user->client_id === null) {
            return [
                'success' => false,
                'message' => 'Akun ini tidak memiliki akses klien. Hubungi admin.',
            ];
        }

        // --- Reject if chat_id already belongs to another user (answer 3=b) ---
        $existingOwner = User::query()
            ->where('telegram_chat_id', $chatId)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($existingOwner) {
            return [
                'success' => false,
                'message' => 'Akun Telegram ini sudah terhubung ke pengguna lain. Hubungi admin untuk melepaskan tautan terlebih dahulu.',
            ];
        }

        // --- Reject if user already has a different chat_id linked ---
        if ($user->telegram_chat_id !== null && $user->telegram_chat_id !== $chatId) {
            return [
                'success' => false,
                'message' => 'Akun Anda sudah terhubung ke Telegram yang berbeda. Hubungi admin untuk mereset koneksi.',
            ];
        }

        // --- Link the account inside a transaction ---
        DB::transaction(function () use ($record, $user, $chatId): void {
            $user->update(['telegram_chat_id' => $chatId]);
            $record->update(['used_at' => now()]);
        });

        Log::info('TelegramRegistrationService: account linked.', [
            'user_id' => $user->id,
            'client_id' => $user->client_id,
            'chat_id' => $chatId,
        ]);

        return [
            'success' => true,
            'message' => "✅ Berhasil! Akun Anda ({$user->client->name}) telah terhubung ke Telegram. Selamat datang di Lukla Operations Bot!",
            'user' => $user,
        ];
    }

    /**
     * Check whether a text message is a /start command with a token.
     *
     * Returns the token string if matched, null otherwise.
     */
    public function extractStartToken(string $text): ?string
    {
        // Matches: /start TOKEN or /start@BotName TOKEN
        if (preg_match('/^\/start(?:@\S+)?\s+([A-Z0-9]{8,20})$/i', trim($text), $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    /**
     * Check whether a text message is a bare /start command (no token).
     */
    public function isPlainStart(string $text): bool
    {
        return (bool) preg_match('/^\/start(?:@\S+)?$/i', trim($text));
    }
}
