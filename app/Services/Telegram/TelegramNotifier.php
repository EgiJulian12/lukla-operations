<?php

namespace App\Services\Telegram;

use App\Jobs\SendTelegramMessage;
use App\Models\Client;
use App\Models\Order;
use App\Models\SampleRevision;
use App\Models\User;

/**
 * Pengirim notifikasi Telegram asinkron (queue database).
 * Tidak mengirim bila penerima belum menautkan akun Telegram — pengiriman
 * gagal bukan alasan membatalkan transaksi domain.
 */
class TelegramNotifier
{
    public function __construct(private readonly TelegramBotConfig $config) {}

    public function send(string $bot, int|string $chatId, string $text, ?array $replyMarkup = null): void
    {
        if ($this->config->token($bot) === '' || $chatId === '' || $chatId === 0) {
            return;
        }

        SendTelegramMessage::dispatch($bot, $chatId, $text, $replyMarkup);
    }

    /**
     * Kirim ke seluruh user klien yang menautkan Telegram (bot client).
     *
     * @param  ?array<int, array<int, array<string, string>>>  $replyMarkup
     */
    public function notifyClientUsers(Client $client, string $text, ?array $replyMarkup = null): void
    {
        $recipients = User::query()
            ->where('client_id', $client->getKey())
            ->where('is_active', true)
            ->whereNotNull('telegram_chat_id')
            ->pluck('telegram_chat_id');

        foreach ($recipients as $chatId) {
            $this->send('client', $chatId, $text, $replyMarkup);
        }
    }

    /**
     * Penawaran baru selesai dibuat: klien diminta menyetujui via bot.
     */
    public function notifyQuotationProvided(Order $order): void
    {
        $this->notifyClientUsers(
            $order->client,
            sprintf(
                "Penawaran baru untuk order %s.\nEstimasi biaya: %s\n%s\n\nSilakan balas di Telegram ini untuk menyetujui atau menolak.",
                $order->order_number,
                $this->formatRupiah($order->estimated_cost),
                $order->quote_notes ?? '',
            ),
            [[
                ['text' => 'Setujui', 'callback_data' => "approve_quote:{$order->getKey()}"],
                ['text' => 'Tolak', 'callback_data' => "reject_quote:{$order->getKey()}"],
            ]],
        );
    }

    public function notifyQuotationApproved(Order $order): void
    {
        $this->notifyAllInternalUsers(sprintf('Order %s: penawaran disetujui klien.', $order->order_number));
    }

    public function notifyQuotationRejected(Order $order): void
    {
        $this->notifyAllInternalUsers(
            sprintf('Order %s: penawaran ditolak klien, order kembali ke status permintaan.', $order->order_number),
        );
    }

    public function notifySampleRevisionApproved(SampleRevision $revision): void
    {
        $this->notifyAllInternalUsers(
            sprintf('Sample %s revisi v%d: disetujui.', $revision->sample?->code, $revision->version),
        );
    }

    public function notifySampleRevisionRejected(SampleRevision $revision): void
    {
        $this->notifyAllInternalUsers(
            sprintf('Sample %s revisi v%d: ditolak.', $revision->sample?->code, $revision->version),
        );
    }

    private function notifyAllInternalUsers(string $text): void
    {
        $recipients = User::query()
            ->whereNull('client_id')
            ->where('is_active', true)
            ->whereNotNull('telegram_chat_id')
            ->pluck('telegram_chat_id');

        foreach ($recipients as $chatId) {
            $this->send('owner', $chatId, $text);
        }
    }

    private function formatRupiah(mixed $amount): string
    {
        return 'Rp'.number_format((float) $amount, 0, ',', '.');
    }
}
