<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Ai\DatabaseQueryService;
use App\Services\Ai\IntentRouter;
use App\Services\Ai\RagService;
use App\Services\Ai\TelegramRagService;
use App\Services\Ai\TelegramUserResolver;
use Database\Factories\ClientFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const VALID_SECRET = 'test-webhook-secret';

    private const BOT_TOKEN = 'test-bot-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.webhook_secret' => self::VALID_SECRET,
            'services.telegram.bot_token' => self::BOT_TOKEN,
            'services.telegram.api_url' => 'https://api.telegram.org',
            // Bedrock config is needed so TelegramRagService can be resolved
            // by the container before the mock is swapped in.
            'services.bedrock' => [
                'key' => 'test-key',
                'secret' => 'test-secret',
                's3_region' => 'ap-southeast-1',
                'bedrock_region' => 'us-east-1',
                'bucket' => 'test-bucket',
                'model_id' => 'amazon.nova-lite-v1:0',
                'max_tokens' => 100,
                'temperature' => 0.1,
                'top_k' => 3,
                'chunk_size' => 600,
                'chunk_overlap' => 100,
                'cache_ttl' => 60,
            ],
        ]);

        Role::firstOrCreate(['name' => UserRole::CLIENT_MAKLON->value, 'guard_name' => 'web']);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);
    }

    protected function tearDown(): void
    {
        // Remove any service container instance overrides so they don't
        // bleed into subsequent tests in the same test run.
        $this->app->forgetInstance(TelegramUserResolver::class);
        $this->app->forgetInstance(IntentRouter::class);
        $this->app->forgetInstance(DatabaseQueryService::class);
        $this->app->forgetInstance(TelegramRagService::class);
        $this->app->forgetInstance(RagService::class);

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Middleware: signature validation
    // -----------------------------------------------------------------------

    public function test_returns_403_when_secret_token_header_is_missing(): void
    {
        $response = $this->postJson('/api/telegram/webhook', $this->buildUpdate());

        $response->assertStatus(403);
        $response->assertJson(['ok' => false]);
    }

    public function test_returns_403_when_secret_token_is_wrong(): void
    {
        $response = $this->postJson(
            '/api/telegram/webhook',
            $this->buildUpdate(),
            ['X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret'],
        );

        $response->assertStatus(403);
    }

    public function test_returns_200_when_secret_token_is_correct(): void
    {
        // A resolver that returns null causes the "not registered" path — still 200.
        $resolver = Mockery::mock(TelegramUserResolver::class);
        $resolver->shouldReceive('resolve')->andReturn(null);
        $this->app->instance(TelegramUserResolver::class, $resolver);

        $response = $this->withValidToken()->postJson(
            '/api/telegram/webhook',
            $this->buildUpdate(),
        );

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    // -----------------------------------------------------------------------
    // Access control: unlinked chat ID
    // -----------------------------------------------------------------------

    public function test_sends_not_registered_message_for_unknown_chat_id(): void
    {
        $resolver = Mockery::mock(TelegramUserResolver::class);
        $resolver->shouldReceive('resolve')->with('99999')->andReturn(null);
        $this->app->instance(TelegramUserResolver::class, $resolver);

        $response = $this->withValidToken()->postJson(
            '/api/telegram/webhook',
            $this->buildUpdate(chatId: '99999'),
        );

        $response->assertStatus(200);

        Http::assertSent(fn ($request) => str_contains($request->body(), 'belum terhubung'));
    }

    // -----------------------------------------------------------------------
    // Intent routing
    // -----------------------------------------------------------------------

    public function test_doc_intent_routes_to_rag_service(): void
    {
        $user = $this->createLinkedClientUser(chatId: '11111');

        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('askQuestion')
            ->once()
            ->with(Mockery::type('string'))
            ->andReturn(['answer' => 'Doc answer', 'sources' => [], 'model_id' => 'test', 'session_id' => 'abc']);
        $ragService->shouldNotReceive('getRelevantChunksForQuery');

        $telegramRagService = Mockery::mock(TelegramRagService::class);
        $telegramRagService->shouldNotReceive('ask');

        $dbService = Mockery::mock(DatabaseQueryService::class);
        $dbService->shouldNotReceive('query');

        $intentRouter = Mockery::mock(IntentRouter::class);
        $intentRouter->shouldReceive('detectIntent')
            ->andReturn(['intent' => 'doc', 'model' => null, 'filters' => []]);

        $resolver = Mockery::mock(TelegramUserResolver::class);
        $resolver->shouldReceive('resolve')->andReturn($user);

        $this->app->instance(RagService::class, $ragService);
        $this->app->instance(TelegramRagService::class, $telegramRagService);
        $this->app->instance(IntentRouter::class, $intentRouter);
        $this->app->instance(DatabaseQueryService::class, $dbService);
        $this->app->instance(TelegramUserResolver::class, $resolver);

        $response = $this->withValidToken()->postJson(
            '/api/telegram/webhook',
            $this->buildUpdate(chatId: '11111', text: 'Apa itu proses produksi?'),
        );

        $response->assertStatus(200);
    }

    public function test_db_intent_routes_to_telegram_rag_service_with_db_context(): void
    {
        $user = $this->createLinkedClientUser(chatId: '22222');

        $dbData = ['model' => 'orders', 'count' => 1, 'data' => [['order_number' => 'ORD-001', 'status' => 'Dalam Produksi']]];

        $dbService = Mockery::mock(DatabaseQueryService::class);
        $dbService->shouldReceive('query')
            ->once()
            ->andReturn($dbData);

        $telegramRagService = Mockery::mock(TelegramRagService::class);
        $telegramRagService->shouldReceive('ask')
            ->zeroOrMoreTimes()
            ->andReturn(['answer' => 'Your order is in production.', 'sources' => [], 'model_id' => 'test', 'session_id' => 'abc']);

        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldNotReceive('askQuestion');

        $intentRouter = Mockery::mock(IntentRouter::class);
        $intentRouter->shouldReceive('detectIntent')
            ->andReturn(['intent' => 'db', 'model' => 'orders', 'filters' => []]);

        $resolver = Mockery::mock(TelegramUserResolver::class);
        $resolver->shouldReceive('resolve')->andReturn($user);

        $this->app->instance(RagService::class, $ragService);
        $this->app->instance(TelegramRagService::class, $telegramRagService);
        $this->app->instance(IntentRouter::class, $intentRouter);
        $this->app->instance(DatabaseQueryService::class, $dbService);
        $this->app->instance(TelegramUserResolver::class, $resolver);

        $response = $this->withValidToken()->postJson(
            '/api/telegram/webhook',
            $this->buildUpdate(chatId: '22222', text: 'Lihat pesanan saya'),
        );

        $response->assertStatus(200);
    }

    public function test_hybrid_intent_routes_to_telegram_rag_service_with_both_contexts(): void
    {
        $user = $this->createLinkedClientUser(chatId: '33333');

        $dbData = ['model' => 'orders', 'count' => 1, 'data' => [['order_number' => 'ORD-002', 'status' => 'Selesai']]];
        $s3Chunks = [['content' => 'doc chunk', 'source' => 'doc.md', 'chunk_index' => 0, 'score' => 1.5]];

        $dbService = Mockery::mock(DatabaseQueryService::class);
        $dbService->shouldReceive('query')->once()->andReturn($dbData);

        $ragService = Mockery::mock(RagService::class);
        $ragService->shouldReceive('getRelevantChunksForQuery')->once()->andReturn($s3Chunks);
        $ragService->shouldNotReceive('askQuestion');

        $telegramRagService = Mockery::mock(TelegramRagService::class);
        $telegramRagService->shouldReceive('ask')
            ->once()
            ->with(Mockery::type('string'), $dbData, $s3Chunks)
            ->andReturn(['answer' => 'Hybrid answer.', 'sources' => [], 'model_id' => 'test', 'session_id' => 'abc']);

        $intentRouter = Mockery::mock(IntentRouter::class);
        $intentRouter->shouldReceive('detectIntent')
            ->andReturn(['intent' => 'hybrid', 'model' => 'orders', 'filters' => []]);

        $resolver = Mockery::mock(TelegramUserResolver::class);
        $resolver->shouldReceive('resolve')->andReturn($user);

        $this->app->instance(RagService::class, $ragService);
        $this->app->instance(TelegramRagService::class, $telegramRagService);
        $this->app->instance(IntentRouter::class, $intentRouter);
        $this->app->instance(DatabaseQueryService::class, $dbService);
        $this->app->instance(TelegramUserResolver::class, $resolver);

        $response = $this->withValidToken()->postJson(
            '/api/telegram/webhook',
            $this->buildUpdate(chatId: '33333', text: 'Bagaimana proses pesanan saya?'),
        );

        $response->assertStatus(200);
    }

    public function test_always_returns_200_even_when_service_throws(): void
    {
        $resolver = Mockery::mock(TelegramUserResolver::class);
        $resolver->shouldReceive('resolve')->andThrow(new \RuntimeException('Service down'));
        $this->app->instance(TelegramUserResolver::class, $resolver);

        $response = $this->withValidToken()->postJson(
            '/api/telegram/webhook',
            $this->buildUpdate(chatId: '44444'),
        );

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    public function test_replies_with_apology_when_processing_throws(): void
    {
        $resolver = Mockery::mock(TelegramUserResolver::class);
        $resolver->shouldReceive('resolve')->andThrow(new \RuntimeException('Bedrock down'));
        $this->app->instance(TelegramUserResolver::class, $resolver);

        $this->withValidToken()->postJson(
            '/api/telegram/webhook',
            $this->buildUpdate(chatId: '55555'),
        )->assertStatus(200);

        // A processing failure must still reach the client — never silence.
        Http::assertSent(fn ($request) => str_contains($request->body(), 'kendala teknis'));
    }

    public function test_returns_200_when_bot_api_is_unreachable(): void
    {
        $resolver = Mockery::mock(TelegramUserResolver::class);
        $resolver->shouldReceive('resolve')->andReturn(null);
        $this->app->instance(TelegramUserResolver::class, $resolver);

        Http::fake(fn () => throw new ConnectionException('cURL error 60: SSL certificate problem'));

        $this->withValidToken()->postJson(
            '/api/telegram/webhook',
            $this->buildUpdate(chatId: '66666'),
        )
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }

    public function test_returns_200_immediately_for_non_message_updates(): void
    {
        $response = $this->withValidToken()->postJson(
            '/api/telegram/webhook',
            ['update_id' => 1, 'callback_query' => ['id' => '123']],
        );

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function buildUpdate(string $chatId = '12345678', string $text = 'Halo'): array
    {
        return [
            'update_id' => 1000,
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => (int) $chatId, 'type' => 'private'],
                'from' => ['id' => (int) $chatId, 'first_name' => 'Test'],
                'text' => $text,
                'date' => now()->timestamp,
            ],
        ];
    }

    private function withValidToken(): static
    {
        return $this->withHeaders([
            'X-Telegram-Bot-Api-Secret-Token' => self::VALID_SECRET,
        ]);
    }

    private function createLinkedClientUser(string $chatId): User
    {
        $client = ClientFactory::new()->create();

        $user = UserFactory::new()->create([
            'telegram_chat_id' => $chatId,
            'is_active' => true,
            'client_id' => $client->id,
        ]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        return $user;
    }
}
