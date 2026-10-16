<?php

namespace Tests\Feature\Telegram;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use App\Services\ClientBot\BedrockClientBotService;
use App\Services\ClientBot\ClientDataService;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use ReflectionClass;

/**
 * Jalur pesan bebas (non-perintah) client bot → Asisten AI Bedrock:
 * otorisasi akun, rate limit, dan fallback saat Bedrock gagal.
 */
final class TelegramClientAiTest extends TelegramTestCase
{
    public function test_free_text_from_bound_client_reaches_the_ai_agent(): void
    {
        $user = $this->boundClientUser();

        $this->mock(BedrockClientBotService::class)
            ->shouldReceive('handleMessage')
            ->once()
            ->withArgs(fn (User $agentUser, string $text): bool => (int) $agentUser->client_id === (int) $user->client_id
                && $text === 'status order saya')
            ->andReturn('Ini jawaban asisten AI.');

        $this->postUpdate('client', $this->messageUpdate('status order saya'));

        $texts = implode("\n", $this->sentTexts());
        $this->assertStringContainsString('Ini jawaban asisten AI.', $texts);
    }

    public function test_free_text_from_unbound_user_never_calls_the_agent(): void
    {
        $this->mock(BedrockClientBotService::class)
            ->shouldNotReceive('handleMessage');

        $this->postUpdate('client', $this->messageUpdate('status order saya'));

        $texts = implode("\n", $this->sentTexts());
        $this->assertStringContainsString('belum tertaut', $texts);
    }

    public function test_free_text_is_rate_limited_per_chat(): void
    {
        $this->boundClientUser();

        $key = 'ai-telegram:7001';
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit($key, 60);
        }

        $this->mock(BedrockClientBotService::class)
            ->shouldNotReceive('handleMessage');

        $this->postUpdate('client', $this->messageUpdate('pertanyaan ke-21'));

        $texts = implode("\n", $this->sentTexts());
        $this->assertStringContainsString('terlalu cepat', $texts);
    }

    public function test_bedrock_unavailable_replies_with_fallback_apology(): void
    {
        $dataService = Mockery::mock(ClientDataService::class);

        $bedrock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrock->shouldReceive('converse')->andThrow(new \RuntimeException('bedrock down'));

        $service = new BedrockClientBotService($dataService);
        $property = (new ReflectionClass($service))->getProperty('bedrock');
        $property->setAccessible(true);
        $property->setValue($service, $bedrock);

        $user = User::factory()->make(['client_id' => 1]);

        $this->assertStringContainsString('gangguan', $service->handleMessage($user, 'status order saya'));
    }

    private function boundClientUser(): User
    {
        $client = Client::factory()->create();
        $user = User::factory()->for($client, 'client')->create([
            'telegram_chat_id' => '7001',
            'telegram_user_id' => '7001',
        ]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);
        $user->refresh();

        return $user;
    }
}
