<?php

namespace Tests\Feature\Telegram;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\OwnerBot\BedrockOwnerBotService;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Jalur pesan bebas owner bot → Asisten AI Owner (Bedrock tools lintas
 * klien) dan pembatasan scope-nya hanya ke role OWNER_EXECUTIVE.
 */
final class TelegramOwnerAiTest extends TelegramTestCase
{
    public function test_free_text_from_owner_reaches_the_owner_agent(): void
    {
        $this->boundRoleUser(UserRole::OWNER_EXECUTIVE);

        $this->mock(BedrockOwnerBotService::class)
            ->shouldReceive('handleMessage')
            ->once()
            ->withArgs(fn (string $text, ?string $chatId): bool => $text === 'ringkasan pabrik' && $chatId === '7001')
            ->andReturn(['reply' => '*Ringkasan pabrik hari ini.']);

        $this->postUpdate('owner', $this->messageUpdate('ringkasan pabrik'));

        $texts = implode("\n", $this->sentTexts());
        $this->assertStringContainsString('Ringkasan pabrik hari ini.', $texts);
    }

    public function test_non_owner_staff_cannot_use_the_owner_agent(): void
    {
        $this->boundRoleUser(UserRole::WAREHOUSE);

        $this->mock(BedrockOwnerBotService::class)
            ->shouldNotReceive('handleMessage');

        $this->postUpdate('owner', $this->messageUpdate('ringkasan pabrik'));

        $texts = implode("\n", $this->sentTexts());
        $this->assertStringContainsString('hanya untuk akun Owner', $texts);
    }

    public function test_free_text_from_unbound_user_never_calls_the_agent(): void
    {
        $this->mock(BedrockOwnerBotService::class)
            ->shouldNotReceive('handleMessage');

        $this->postUpdate('owner', $this->messageUpdate('ringkasan pabrik'));

        $texts = implode("\n", $this->sentTexts());
        $this->assertStringContainsString('belum tertaut', $texts);
    }

    public function test_owner_free_text_is_rate_limited_per_chat(): void
    {
        $this->boundRoleUser(UserRole::OWNER_EXECUTIVE);

        $key = 'ai-telegram-owner:7001';
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit($key, 60);
        }

        $this->mock(BedrockOwnerBotService::class)
            ->shouldNotReceive('handleMessage');

        $this->postUpdate('owner', $this->messageUpdate('pertanyaan ke-21'));

        $texts = implode("\n", $this->sentTexts());
        $this->assertStringContainsString('terlalu cepat', $texts);
    }

    private function boundRoleUser(UserRole $role): User
    {
        $user = User::factory()->create([
            'telegram_chat_id' => '7001',
            'telegram_user_id' => '7001',
        ]);
        $user->assignRole($role->value);
        $user->refresh();

        return $user;
    }
}
