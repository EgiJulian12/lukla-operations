<?php

namespace Tests\Feature\Telegram;

use App\Models\TelegramLinkToken;
use App\Services\Telegram\TelegramBindingService;

/**
 * Binding token sekali-pakai: valid, kedaluwarsas, sudah dipakai, dan salah bot.
 */
final class TelegramBindingTest extends TelegramTestCase
{
    public function test_bound_token_links_user_and_sets_identity_columns(): void
    {
        $user = $this->clientUser();

        $service = app(TelegramBindingService::class);
        $url = $service->startUrlFor($user);

        $this->assertStringContainsString('https://t.me/', $url);
        $raw = $this->excerptRawToken($url);

        $bound = $service->bindByToken($raw, 7_001, 7_001, 'client');

        $this->assertNotNull($bound);
        $this->assertSame((string) 7_001, (string) $bound->telegram_chat_id);
        $this->assertSame((string) 7_001, (string) $bound->telegram_user_id);
        $this->assertNotNull($bound->telegram_linked_at);
    }

    public function test_token_consumed_after_use(): void
    {
        $user = $this->clientUser();

        $service = app(TelegramBindingService::class);
        $raw = $this->excerptRawToken($service->startUrlFor($user));

        $this->assertNotNull($service->bindByToken($raw, 7_001, 7_001, 'client'));
        $this->assertNull($service->bindByToken($raw, 7_001, 7_001, 'client'), 'Token dipakai dua kali tidak boleh sah.');
    }

    public function test_expired_token_rejected(): void
    {
        $user = $this->clientUser();

        $service = app(TelegramBindingService::class);
        $service->startUrlFor($user);

        $record = TelegramLinkToken::query()->latest('id')->first();
        $record->update(['expires_at' => now()->subMinute()]);

        $raw = $this->excerptRawToken('https://t.me/lukla_client_test_bot?start=rawtoken');
        $this->assertNull($service->bindByToken($raw, 7_001, 7_001, 'client'));
    }

    public function test_token_from_client_bot_cannot_be_used_on_owner_bot(): void
    {
        $user = $this->clientUser();

        $service = app(TelegramBindingService::class);
        $raw = $this->excerptRawToken($service->startUrlFor($user));

        $this->assertNull($service->bindByToken($raw, 7_001, 7_001, 'owner'));

        $user->refresh();
        $this->assertNull($user->telegram_chat_id, 'Token tetap tanpa tautan setelah percobaan di bot yang salah.');
    }

    public function test_unknown_token_rejected(): void
    {
        $service = app(TelegramBindingService::class);

        $this->assertNull($service->bindByToken('token-tak-dikenal-cukup-panjang', 7_001, 7_001, 'client'));
    }

    /**
     * Raw token adalah parameter URL ?start=...
     */
    private function excerptRawToken(string $url): string
    {
        $this->assertMatchesRegularExpression('/\?start=\w+/', $url);

        return substr($url, strpos($url, '?start=') + strlen('?start='));
    }
}
