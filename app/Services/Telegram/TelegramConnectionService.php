<?php

namespace App\Services\Telegram;

use Telegram\Bot\Laravel\Facades\Telegram;

/**
 * Pendaftaran dan pembongkaran webhook di sisi Telegram.
 * secret_token dipasang saat setWebhook sehingga Telegram membawanya kembali
 * pada header X-Telegram-Bot-Api-Secret-Token di setiap update.
 */
class TelegramConnectionService
{
    public function __construct(private readonly TelegramBotConfig $config) {}

    public function setWebhook(string $bot): bool
    {
        $secret = $this->config->webhookSecret($bot);

        if (rtrim((string) config('telegram.app_url'), '/') === '' || $secret === '') {
            return false;
        }

        $url = sprintf('%s/telegram/%s/%s', rtrim((string) config('telegram.app_url'), '/'), $bot, $secret);

        return Telegram::bot($bot)->setWebhook([
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => config("telegram.bots.{$bot}.allowed_updates"),
        ]);
    }

    public function deleteWebhook(string $bot): bool
    {
        return Telegram::bot($bot)->deleteWebhook();
    }

    public function webhookUrl(string $bot): string
    {
        return sprintf(
            '%s/telegram/%s/%s',
            rtrim((string) config('telegram.app_url'), '/'),
            $bot,
            $this->config->webhookSecret($bot),
        );
    }
}
