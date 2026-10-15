<?php

namespace Tests\Feature\Telegram;

use App\Enums\OrderStatus;
use App\Enums\SampleStatus;
use App\Enums\UserRole;
use App\Jobs\SendTelegramMessage;
use App\Models\Order;
use App\Models\SampleRevision;
use App\Services\Order\OrderQuotationService;
use App\Services\Sample\SampleService;
use Illuminate\Support\Facades\Queue;

/**
 * Notifikasi keluar: penawaran ke klien (bot client, tombol setuju/tolak),
 * hasil keputusan klien ke staf internal (bot owner), dan status sample.
 */
final class TelegramNotificationsTest extends TelegramTestCase
{
    public function test_quotation_provided_queues_message_with_buttons_to_bound_client_users(): void
    {
        Queue::fake();

        $order = $this->orderForClientWithBoundUser(OrderStatus::REQUESTED);

        app(OrderQuotationService::class)->provideQuote($order, 750_000, 'Estimasi tahap awal');

        Queue::assertPushed(SendTelegramMessage::class, function (SendTelegramMessage $job): bool {
            return $job->bot === 'client'
                && $job->chatId === '7001'
                && str_contains($job->text, 'Penawaran baru untuk order')
                && $job->replyMarkup !== null
                && str_contains(json_encode($job->replyMarkup), 'approve_quote');
        });
    }

    public function test_client_without_telegram_link_receives_no_message(): void
    {
        Queue::fake();

        $order = $this->orderForClientWithBoundUser(OrderStatus::REQUESTED, linkUser: false);

        app(OrderQuotationService::class)->provideQuote($order, 750_000, null);

        Queue::assertNotPushed(SendTelegramMessage::class);
    }

    public function test_quotation_approval_notifies_internal_users_on_owner_bot(): void
    {
        Queue::fake();

        $this->internalUser(UserRole::OWNER_EXECUTIVE, [
            'telegram_chat_id' => '5100',
            'telegram_user_id' => '5100',
        ]);

        $order = $this->orderForClientWithBoundUser(OrderStatus::QUOTED);

        app(OrderQuotationService::class)->approveQuote($order);

        Queue::assertPushed(SendTelegramMessage::class, fn (SendTelegramMessage $job): bool => $job->bot === 'owner'
            && $job->chatId === '5100'
            && str_contains($job->text, 'penawaran disetujui klien'));
    }

    public function test_sample_revision_approval_notifies_owner_bot_users(): void
    {
        Queue::fake();

        $this->internalUser(UserRole::SAMPLE_TEAM, [
            'telegram_chat_id' => '5200',
            'telegram_user_id' => '5200',
        ]);

        $revision = SampleRevision::factory()->create(['status' => SampleStatus::IN_REVIEW]);

        app(SampleService::class)->approveRevision($revision);

        Queue::assertPushed(SendTelegramMessage::class, fn (SendTelegramMessage $job): bool => $job->bot === 'owner'
            && $job->chatId === '5200');
    }

    private function orderForClientWithBoundUser(OrderStatus $status, bool $linkUser = true): Order
    {
        $user = $linkUser
            ? $this->clientUser([
                'telegram_chat_id' => '7001',
                'telegram_user_id' => '7001',
            ])
            : $this->clientUser();

        return Order::factory()->for($user->client, 'client')->create([
            'order_number' => 'REQ-202610-0800',
            'status' => $status,
        ]);
    }
}
