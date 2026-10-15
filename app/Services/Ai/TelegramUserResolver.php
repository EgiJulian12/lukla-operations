<?php

namespace App\Services\Ai;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Resolves a Telegram chat ID to an authenticated, active CLIENT_MAKLON user.
 *
 * This is the access-control gate for the Telegram bot: a chat ID that is not
 * linked to an active client account receives no data.
 */
class TelegramUserResolver
{
    /**
     * Resolve a Telegram chat ID to a User.
     *
     * Returns null if:
     *  - No user has this telegram_chat_id
     *  - The user is inactive (is_active = false)
     *  - The user does not have the CLIENT_MAKLON role
     *  - The user has no client_id bound (orphaned account)
     */
    public function resolve(string|int $chatId): ?User
    {
        $user = User::query()
            ->where('telegram_chat_id', (string) $chatId)
            ->where('is_active', true)
            ->first();

        if ($user === null) {
            return null;
        }

        if (! $user->hasRole(UserRole::CLIENT_MAKLON->value)) {
            return null;
        }

        if ($user->client_id === null) {
            return null;
        }

        return $user;
    }
}
