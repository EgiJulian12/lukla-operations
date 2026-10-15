<?php

namespace Tests\Feature\Telegram;

use App\Enums\OrderStatus;
use App\Enums\SampleStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\SampleRevision;
use App\Models\User;

/**
 * Tombol persetujuan: klien menyetujui/menolak penawaran melalui bot client,
 * owner menyetujui/menolak revisi sample melalui bot owner. Semua lewat
 * service domain (audit log tetap ditulis), dengan cek wewenang antarklien.
 */
final class TelegramApprovalFlowTest extends TelegramTestCase
{
    public function test_bound_client_approves_own_quote_via_callback(): void
    {
        $order = $this->quotedOrderForNewClient();

        $user = User::query()->where('client_id', $order->client_id)->whereNotNull('telegram_chat_id')->first();

        $response = $this->postUpdate('client', $this->callbackUpdate("approve_quote:{$order->getKey()}", (int) $user->telegram_user_id));

        $response->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::QUOTE_APPROVED->value, $order->status->value);

        $this->assertTrue(
            AuditLog::query()->where('event', 'quotation_approved')->where('order_id', $order->getKey())->exists(),
        );
    }

    public function test_another_clients_user_cannot_approve_someone_elses_quote(): void
    {
        $order = $this->quotedOrderForNewClient();

        $intruder = $this->clientUser([
            'telegram_chat_id' => '7002',
            'telegram_user_id' => '7002',
        ]);

        $response = $this->postUpdate('client', $this->callbackUpdate("approve_quote:{$order->getKey()}", (int) $intruder->telegram_user_id));

        $response->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::QUOTED->value, $order->status->value);

        $this->assertFalse(
            AuditLog::query()->where('event', 'quotation_approved')->where('order_id', $order->getKey())->exists(),
        );

        $texts = implode("\n", $this->sentTexts());
        $this->assertStringContainsString('tidak berwenang', $texts);
    }

    public function test_unbound_telegram_user_cannot_approve_anything(): void
    {
        $order = $this->quotedOrderForNewClient();

        $this->postUpdate('client', $this->callbackUpdate("approve_quote:{$order->getKey()}", 9_999_999));

        $order->refresh();
        $this->assertSame(OrderStatus::QUOTED->value, $order->status->value);
        $this->assertSame([], $this->sentTexts());
    }

    public function test_owner_role_approves_sample_revision_via_owner_bot(): void
    {
        $revision = SampleRevision::factory()->create([
            'status' => SampleStatus::IN_REVIEW,
        ]);

        $approver = $this->internalUser(UserRole::OWNER_EXECUTIVE, [
            'telegram_chat_id' => '7003',
            'telegram_user_id' => '7003',
        ]);

        $this->postUpdate('owner', $this->callbackUpdate("approve_sample:{$revision->getKey()}", (int) $approver->telegram_user_id));

        $revision->refresh();

        $this->assertSame(SampleStatus::APPROVED->value, $revision->status->value);
        $this->assertSame($approver->getKey(), $revision->approved_by);
    }

    public function test_warehouse_role_cannot_approve_sample_revision(): void
    {
        $revision = SampleRevision::factory()->create([
            'status' => SampleStatus::IN_REVIEW,
        ]);

        $approver = $this->internalUser(UserRole::WAREHOUSE, [
            'telegram_chat_id' => '7004',
            'telegram_user_id' => '7004',
        ]);

        $this->postUpdate('owner', $this->callbackUpdate("approve_sample:{$revision->getKey()}", (int) $approver->telegram_user_id));

        $revision->refresh();
        $this->assertSame(SampleStatus::IN_REVIEW->value, $revision->status->value);

        $texts = implode("\n", $this->sentTexts());
        $this->assertStringContainsString('tidak berwenang menyetujui sample', $texts);
    }

    public function test_approving_an_already_processed_transition_replies_with_domain_message(): void
    {
        $order = $this->quotedOrderForNewClient();
        $user = User::query()->where('client_id', $order->client_id)->whereNotNull('telegram_chat_id')->first();

        $this->postUpdate('client', $this->callbackUpdate("approve_quote:{$order->getKey()}", (int) $user->telegram_user_id));

        $before = AuditLog::query()->where('event', 'quotation_approved')->count();

        $this->postUpdate('client', $this->callbackUpdate("approve_quote:{$order->getKey()}", (int) $user->telegram_user_id));

        $this->assertSame($before + 0, AuditLog::query()->where('event', 'quotation_approved')->count());

        $texts = implode("\n", $this->sentTexts());
        $this->assertStringContainsString('belum ada penawaran yang menunggu persetujuan', $texts);
    }

    /**
     * Buat order berstatus QUOTED milik satu klien yang user-nya sudah tertaut.
     */
    private function quotedOrderForNewClient(): Order
    {
        $clientUser = $this->clientUser([
            'telegram_chat_id' => '7001',
            'telegram_user_id' => '7001',
        ]);

        $order = Order::factory()->for($clientUser->client, 'client')->create([
            'order_number' => 'REQ-202610-0700',
            'status' => OrderStatus::QUOTED,
            'estimated_cost' => 1_500_000,
        ]);

        $order->refresh();

        return $order;
    }
}
