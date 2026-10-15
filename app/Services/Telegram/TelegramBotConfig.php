<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Config;

/**
 * Pembaca konfigurasi dua bot Telegram (owner + client) dari config/telegram.php.
 * Isolasi di sini agar pemanggil tidak menyentuh kunci config secara mentah.
 */
class TelegramBotConfig
{
    public function botNames(): array
    {
        return array_keys((array) Config::get('telegram.bots', []));
    }

    public function isValidBot(string $bot): bool
    {
        return Config::get("telegram.bots.{$bot}.token") !== null;
    }

    public function token(string $bot): string
    {
        return (string) Config::get("telegram.bots.{$bot}.token", '');
    }

    public function username(string $bot): string
    {
        return (string) Config::get("telegram.bots.{$bot}.username", '');
    }

    public function webhookSecret(string $bot): string
    {
        return (string) Config::get("telegram.bots.{$bot}.webhook_secret", '');
    }

    /**
     * URL deep link https://t.me/<username>?start=<token>.
     */
    public function startUrl(string $bot, string $rawToken): string
    {
        return sprintf('https://t.me/%s?start=%s', $this->username($bot), $rawToken);
    }
}
