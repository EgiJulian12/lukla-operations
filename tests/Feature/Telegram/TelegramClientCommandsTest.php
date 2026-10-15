<?php

namespace Tests\Feature\Telegram;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Services\Telegram\TelegramBindingService;

/**
 * Perintah dua arah bot client: /start, /orders, /status — dan kebocoran data
 * antar klien yang pasti ditolak (NFR-004).
 */
final class TelegramClientCommandsTest extends TelegramTestCase
{
    public function test_unbound_start_replies_with_pairing_instructions(): void
    {
        $this->postUpdate('client', $this->messageUpdate('/start'));

        $texts = $this->sentTexts();
        $this->assertCount(1, $texts);
        $this->assertStringContainsString('belum tertaut', $texts[0]);
    }

    public function test_start_with_valid_token_links_the_chat(): void
    {
        $user = $this->clientUser();

        $raw = $this->rawTokenFrom($user);

        $this->postUpdate('client', $this->messageUpdate('/start '.$raw));

        $user->refresh();
        $this->assertNotNull($user->telegram_chat_id);

        $texts = $this->sentTexts();
        $this->assertCount(1, $texts);
        $this->assertStringContainsString('berhasil ditautkan', $texts[0]);
    }

    public function test_start_with_wrong_bot_token_is_rejected_reply(): void
    {
        $user = $this->clientUser();
        $raw = $this->rawTokenFrom($user);

        // Token klien dikirim ke bot owner: harus ditolak.
        $this->postUpdate('owner', $this->messageUpdate('/start '.$raw, telegramUserId: 7_001));

        $user->refresh();
        $this->assertNull($user->telegram_chat_id);
    }

    public function test_orders_shows_only_own_clients_data(): void
    {
        $client = $this->clientUser()->client;
        $user = $this->clientUser(['telegram_chat_id' => '7001', 'telegram_user_id' => '7001', 'client_id' => $client->id]);
        $user->refresh();

        Order::factory()->for($client, 'client')->create(['order_number' => 'REQ-202610-0001']);

        $otherClientUser = $this->clientUser(['email' => 'other@lukla.test']);
        Order::factory()->for($otherClientUser->client, 'client')->create(['order_number' => 'REQ-202610-0999']);

        $this->postUpdate('client', $this->messageUpdate('/orders'));

        $texts = implode("\n", $this->sentTexts());

        $this->assertStringContainsString('REQ-202610-0001', $texts);
        $this->assertStringNotContainsString('REQ-202610-0999', $texts, 'Order klien lain tidak boleh bocor.');
    }

    public function test_status_of_other_clients_order_is_not_found(): void
    {
        $user = $this->clientUser([
            'telegram_chat_id' => '7001',
            'telegram_user_id' => '7001',
        ]);
        $user->refresh();

        $otherClientUser = $this->clientUser(['email' => 'other@lukla.test']);
        Order::factory()->for($otherClientUser->client, 'client')->create(['order_number' => 'REQ-202610-0999']);

        $this->postUpdate('client', $this->messageUpdate('/status REQ-202610-0999'));

        $texts = implode("\n", $this->sentTexts());
        $this->assertStringContainsString('tidak ditemukan', $texts);
    }

    public function test_status_shows_detail_including_waiting_approval_note(): void
    {
        $user = $this->clientUser([
            'telegram_chat_id' => '7001',
            'telegram_user_id' => '7001',
        ]);
        $user->refresh();

        Order::factory()
            ->for($user->client, 'client')
            ->create(['order_number' => 'REQ-202610-0002', 'status' => OrderStatus::QUOTED]);

        $this->postUpdate('client', $this->messageUpdate('/status REQ-202610-0002'));

        $texts = implode("\n", $this->sentTexts());

        $this->assertStringContainsString('Menunggu Persetujuan Klien', $texts);
        $this->assertStringContainsString('penawaran', $texts);
    }

    public function test_start_with_a_chat_already_bound_to_another_account_stays_unbound(): void
    {
        // Telegram chat 7001 sudah dipakai akun owner; klien mencoba memakai
        // chat yang sama lewat bot client.
        $owner = $this->internalUser(UserRole::OWNER_EXECUTIVE, [
            'telegram_chat_id' => '7001',
            'telegram_user_id' => '7001',
        ]);

        $client = $this->clientUser();
        $raw = $this->rawTokenFrom($client);

        $this->postUpdate('client', $this->messageUpdate('/start '.$raw));

        $client->refresh();
        $owner->refresh();

        $this->assertNull($client->telegram_chat_id, 'Chat yang sudah dipakai akun lain tidak boleh di-link.');
        $this->assertSame('7001', $owner->telegram_chat_id, 'Binding owner tidak boleh terganggu.');

        $texts = implode("\n", $this->sentTexts());
        $this->assertStringContainsString('sudah tertaut ke akun lain', $texts);
    }

    private function rawTokenFrom($user): string
    {
        $service = app(TelegramBindingService::class);
        $url = $service->startUrlFor($user);

        return substr($url, strpos($url, '?start=') + strlen('?start='));
    }
}
