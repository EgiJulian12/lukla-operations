<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotConfig;
use App\Services\Telegram\TelegramConnectionService;
use Illuminate\Console\Command;

/**
 * Mengelola webhook dua bot Telegram (owner + client) di Telegram API.
 *
 * Dijalankan sekali setiap aplikasi berpindah URL publik, karena Telegram
 * mengirim update ke URL terakhir yang didaftarkan, bukan otomatis mengikuti
 * perubahan domain aplikasi.
 */
class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook
                            {action=set : set | delete | info}
                            {--bot=* : Nama bot yang dituju, default keduanya}';

    protected $description = 'Kelola webhook bot Telegram (set | delete | info)';

    public function handle(
        TelegramBotConfig $config,
        TelegramConnectionService $connection,
    ): int {
        $bots = (array) ($this->option('bot') ?: $config->botNames());

        foreach ($bots as $bot) {
            if (! $config->isValidBot($bot)) {
                $this->error("Bot {$bot} tidak dikenal di config/telegram.php.");

                continue;
            }

            match ($this->argument('action')) {
                'set' => $this->set($connection, $bot),
                'delete' => $this->delete($connection, $bot),
                'info' => $this->info($bot.': '.$connection->webhookUrl($bot)),
                default => null,
            };
        }

        return self::SUCCESS;
    }

    private function set(TelegramConnectionService $connection, string $bot): void
    {
        if ($connection->setWebhook($bot)) {
            $this->info("Webhook {$bot} terpasang: ".$connection->webhookUrl($bot));

            return;
        }

        $this->error("Webhook {$bot} gagal dipasang. Pastikan TELEGRAM_APP_URL dan webhook_secret bot sudah diisi.");
    }

    private function delete(TelegramConnectionService $connection, string $bot): void
    {
        $this->info($connection->deleteWebhook($bot)
            ? "Webhook {$bot} dilepas."
            : "Webhook {$bot} tidak dapat dilepas. Periksa koneksi atau token bot.");
    }
}
