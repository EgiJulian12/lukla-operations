<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single-use token that lets a CLIENT_MAKLON user link their Telegram account.
 *
 * Lifecycle:
 *  1. Admin generates the token in Filament → token is created, expires in 24h.
 *  2. User sends /start <token> to the bot.
 *  3. TelegramRegistrationService validates the token, sets used_at, and writes
 *     telegram_chat_id on the user — in a single transaction.
 *  4. The token can never be reused (used_at is set).
 */
class TelegramRegistrationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tokens that are still valid: not yet used and not expired.
     *
     * @param  Builder<TelegramRegistrationToken>  $query
     * @return Builder<TelegramRegistrationToken>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query
            ->whereNull('used_at')
            ->where('expires_at', '>', now());
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
