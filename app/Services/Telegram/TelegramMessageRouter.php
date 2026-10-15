<?php

namespace App\Services\Telegram;

use App\Enums\UserRole;
use App\Exceptions\InvalidOrderQuotationState;
use App\Exceptions\InvalidSampleState;
use App\Models\Order;
use App\Models\SampleRevision;
use App\Models\User;
use App\Services\Order\OrderQuotationService;
use App\Services\Sample\SampleService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update;
use Throwable;

/**
 * Router update webhook → tindakan domain.
 *
 * Otorisasi memakai User yang sudah ditautkan (via chat/telegram id), bukan
 * auth()->user(), karena webhook tidak punya sesi. Semua query milik klien
 * wajib eksplisit via forClient(): global scope BelongsToClient menonaktifkan
 * diri saat auth() null, seperti pada konteks webhook ini.
 */
class TelegramMessageRouter
{
    public function __construct(
        private readonly TelegramBindingService $binding,
        private readonly TelegramBotConfig $config,
        private readonly OrderQuotationService $quotations,
        private readonly SampleService $samples,
    ) {}

    public function route(string $bot, Update $update): void
    {
        try {
            $this->handle($bot, $update);
        } catch (Throwable $e) {
            Log::error('Update Telegram gagal diproses.', [
                'bot' => $bot,
                'update_id' => $update->updateId,
                'exception' => $e,
            ]);
        }
    }

    private function handle(string $bot, Update $update): void
    {
        match ($update->objectType()) {
            'message' => $this->handleMessage($bot, $update->message),
            'callback_query' => $this->handleCallbackQuery($bot, $update->callbackQuery),
            default => null,
        };
    }

    private function handleMessage(string $bot, $message): void
    {
        $chat = collect($message->get('chat', collect()));

        // Chat pribadi saja; pesan grup diabaikan agar bot tidak berisik.
        if ($chat->get('type') !== 'private') {
            return;
        }

        $chatId = (int) $chat->get('id');
        $telegramUserId = (int) collect($message->get('from', collect()))->get('id', 0);

        [$command, $payload] = $this->parseCommand((string) $message->get('text', ''));

        $user = $this->binding->findUserByChatId($chatId);

        match ($command) {
            'start' => $this->handleStart($bot, $chatId, $telegramUserId, $user, $payload),
            'help' => $this->reply($bot, $chatId, $this->helpText($user)),
            'orders' => $this->handleOrders($bot, $chatId, $user),
            'status' => $this->handleStatus($bot, $chatId, $user, $payload),
            default => null,
        };
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function parseCommand(string $text): array
    {
        if (! str_starts_with($text, '/')) {
            return [null, null];
        }

        // Format Telegram: "/start@BotName <payload>"
        preg_match('/^\/([a-z_]+)(?:@\S+)?(?:\s+(.*))?$/iu', $text, $matches);

        if (! isset($matches[1])) {
            return [null, null];
        }

        return [strtolower($matches[1]), trim($matches[2] ?? '') ?: null];
    }

    private function handleStart(string $bot, int $chatId, int $telegramUserId, ?User $user, ?string $payload): void
    {
        if ($payload !== null) {
            try {
                $linked = $this->binding->bindByToken($payload, $telegramUserId, $chatId, $bot);
            } catch (UniqueConstraintViolationException) {
                // Chat Telegram yang sama menyandang satu binding saja (unique
                // index). Biasanya orang memakai akun Telegram yang sama untuk
                // owner dan klien; kedua binding yaitu berbeda bot pun gagal.
                $this->reply($bot, $chatId, 'Akun Telegram ini sudah tertaut ke akun lain di sistem. Setiap Telegram hanya boleh menautkan satu akun aplikasi — pakai akun Telegram lain untuk akun ini.');

                return;
            }

            if ($linked === null) {
                $this->reply($bot, $chatId, 'Tautan tidak valid atau sudah kedaluwarsa. Buat tautan baru dari panel aplikasi.');

                return;
            }

            $this->reply($bot, $chatId, 'Akun berhasil ditautkan.'."\n\n".$this->helpText($linked));

            return;
        }

        if ($user !== null) {
            $this->reply($bot, $chatId, 'Selamat datang, '.$user->name.".\n\n".$this->helpText($user));

            return;
        }

        $this->reply($bot, $chatId, 'Akun Telegram ini belum tertaut.'."\n".'Buka panel aplikasi lalu pilih "Hubungkan Telegram" untuk mendapatkan tautan pairing.');
    }

    private function helpText(?User $user): string
    {
        if ($user === null || ! $user->isClientUser()) {
            return "Perintah:\n/status <nomor> — detail order\nTombol persetujuan akan muncul pada pesan pengajuan.";
        }

        return "Perintah:\n/orders — ringkasan order Anda\n/status <nomor> — detail satu order";
    }

    private function handleOrders(string $bot, int $chatId, ?User $user): void
    {
        if ($user === null) {
            $this->reply($bot, $chatId, $this->unboundText());

            return;
        }

        $query = Order::query()->orderByDesc('id');

        if ($user->client_id !== null) {
            $query->forClient($user->client_id);
        }

        $orders = $query->limit(10)->get();

        if ($orders->isEmpty()) {
            $this->reply($bot, $chatId, 'Belum ada order.');

            return;
        }

        $body = $orders->map(
            fn (Order $order): string => sprintf('%s — %s', $order->order_number, $order->status->getLabel()),
        )->implode("\n");

        $this->reply($bot, $chatId, '10 order terbaru:'."\n\n".$body."\n\n".'Gunakan /status <nomor> untuk detail.');
    }

    private function handleStatus(string $bot, int $chatId, ?User $user, ?string $orderNumber): void
    {
        if ($orderNumber === null) {
            $this->reply($bot, $chatId, 'Format: /status <nomor order>');

            return;
        }

        if ($user === null) {
            $this->reply($bot, $chatId, $this->unboundText());

            return;
        }

        $query = Order::query()->where('order_number', $orderNumber);

        if ($user->client_id !== null) {
            $query->forClient($user->client_id);
        }

        $order = $query->first();

        if ($order === null) {
            $this->reply($bot, $chatId, 'Order '.$orderNumber.' tidak ditemukan.');

            return;
        }

        $this->reply($bot, $chatId, sprintf(
            "Order %s\nStatus: %s\nSkema: %s\nJumlah unit: %s\n\n%s",
            $order->order_number,
            $order->status->getLabel(),
            $order->work_scheme->value,
            $order->quantity !== null ? number_format((float) $order->quantity, 0, ',', '.') : '-',
            $order->status->awaitingClientApproval()
                ? 'Penawaran menunggu persetujuan Anda — minta notifikasi ulang lewat tombol penawaran atau hubungi owner.'
                : 'Kirim /status '.$order->order_number.' kembali nanti untuk melihat perkembangannya.',
        ));
    }

    private function handleCallbackQuery(string $bot, $callbackQuery): void
    {
        $data = strval($callbackQuery->get('data', ''));

        if ($data === '' || ! str_contains($data, ':')) {
            return;
        }

        [$action, $id] = explode(':', $data, 2);
        $telegramUserId = (int) collect($callbackQuery->get('from', collect()))->get('id', 0);
        $chatId = (int) collect(collect($callbackQuery->get('message', collect()))->get('chat', collect()))->get('id', 0);
        $user = $this->binding->findUserByTelegramId($telegramUserId);

        if ($chatId === 0 || $user === null) {
            return;
        }

        try {
            match ($action) {
                'approve_quote' => $this->handleQuoteDecision($bot, $chatId, (int) $id, $user, true),
                'reject_quote' => $this->handleQuoteDecision($bot, $chatId, (int) $id, $user, false),
                'approve_sample' => $this->handleSampleDecision($bot, $chatId, (int) $id, $user, true),
                'reject_sample' => $this->handleSampleDecision($bot, $chatId, (int) $id, $user, false),
                default => null,
            };
        } catch (InvalidOrderQuotationState|InvalidSampleState $e) {
            // State tidak valid: pesan domain di-forward tanpa mengubah data.
            $this->reply($bot, $chatId, $e->getMessage());
        }
    }

    private function handleQuoteDecision(string $bot, int $chatId, int $orderId, User $user, bool $approve): void
    {
        $order = Order::query()->withoutGlobalScope('client')->whereKey($orderId)->first();

        if ($order === null) {
            return;
        }

        if (! $user->isClientUser() || (int) $order->client_id !== (int) $user->client_id) {
            $this->reply($bot, $chatId, 'Anda tidak berwenang atas order ini.');

            return;
        }

        $result = $approve
            ? $this->quotations->approveQuote($order, $user)
            : $this->quotations->rejectQuote($order);

        $this->reply($bot, $chatId, $approve
            ? sprintf('Order %s: penawaran disetujui.', $result->order_number)
            : sprintf('Order %s: penawaran ditolak, order kembali menjadi permintaan.', $result->order_number));
    }

    private function handleSampleDecision(string $bot, int $chatId, int $revisionId, User $user, bool $approve): void
    {
        $revision = SampleRevision::query()->whereKey($revisionId)->first();

        if ($revision === null) {
            return;
        }

        if (! $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canApproveSamples())) {
            $this->reply($bot, $chatId, 'Peran Anda tidak berwenang menyetujui sample.');

            return;
        }

        $result = $approve
            ? $this->samples->approveRevision($revision, $user)
            : $this->samples->rejectRevision($revision, 'Ditolak via Telegram oleh '.$user->name);

        $this->reply($bot, $chatId, sprintf(
            'Sample %s revisi v%d %s.',
            $result->sample?->code,
            $result->version,
            $approve ? 'disetujui' : 'ditolak',
        ));
    }

    private function reply(string $bot, int $chatId, string $text): void
    {
        Telegram::bot($bot)->sendMessage(['chat_id' => $chatId, 'text' => $text]);
    }

    private function unboundText(): string
    {
        return 'Akun Telegram ini belum tertaut. Buka panel aplikasi lalu pilih "Hubungkan Telegram".';
    }
}
