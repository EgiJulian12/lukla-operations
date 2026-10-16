<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\TelegramRegistrationToken;
use App\Services\Ai\TelegramRegistrationService;
use Database\Factories\ClientFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TelegramRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TelegramRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TelegramRegistrationService::class);

        Role::firstOrCreate(['name' => UserRole::CLIENT_MAKLON->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => UserRole::WAREHOUSE->value, 'guard_name' => 'web']);
    }

    // -----------------------------------------------------------------------
    // generateToken()
    // -----------------------------------------------------------------------

    public function test_generates_a_token_for_a_user(): void
    {
        $client = ClientFactory::new()->create();
        $user = UserFactory::new()->create(['client_id' => $client->id]);

        $result = $this->service->generateToken($user);

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertTrue($result['expires_at']->isFuture());
    }

    public function test_invalidates_previous_token_when_generating_new_one(): void
    {
        $client = ClientFactory::new()->create();
        $user = UserFactory::new()->create(['client_id' => $client->id]);

        $first = $this->service->generateToken($user);
        $second = $this->service->generateToken($user);

        $this->assertNotSame($first['token'], $second['token']);

        // First token should no longer be valid
        $oldRecord = TelegramRegistrationToken::query()
            ->where('token', $first['token'])
            ->first();

        $this->assertFalse($oldRecord->isValid());
    }

    // -----------------------------------------------------------------------
    // extractStartToken() and isPlainStart()
    // -----------------------------------------------------------------------

    public function test_extracts_token_from_start_command(): void
    {
        $token = $this->service->extractStartToken('/start ABC123XYZ');

        $this->assertSame('ABC123XYZ', $token);
    }

    public function test_returns_null_for_plain_start(): void
    {
        $token = $this->service->extractStartToken('/start');

        $this->assertNull($token);
    }

    public function test_detects_plain_start(): void
    {
        $this->assertTrue($this->service->isPlainStart('/start'));
        $this->assertFalse($this->service->isPlainStart('/start TOKEN123'));
    }

    // -----------------------------------------------------------------------
    // buildDeepLink()
    // -----------------------------------------------------------------------

    public function test_builds_deep_link_with_token_as_start_payload(): void
    {
        config(['telegram.bots.client.username' => 'LuklaClientBot']);

        $link = $this->service->buildDeepLink('abc123xyz');

        $this->assertSame('https://t.me/LuklaClientBot?start=ABC123XYZ', $link);
    }

    public function test_payload_telegram_sends_back_from_deep_link_is_extractable(): void
    {
        $client = ClientFactory::new()->create();
        $user = UserFactory::new()->create(['client_id' => $client->id]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $token = $this->service->generateToken($user)['token'];
        $link = $this->service->buildDeepLink($token);

        // Telegram delivers the ?start= payload as a literal "/start <payload>" message.
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        $this->assertSame($token, $this->service->extractStartToken("/start {$query['start']}"));
    }

    // -----------------------------------------------------------------------
    // register()
    // -----------------------------------------------------------------------

    public function test_links_chat_id_with_valid_token(): void
    {
        $client = ClientFactory::new()->create();
        $user = UserFactory::new()->create(['client_id' => $client->id, 'telegram_chat_id' => null]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $tokenResult = $this->service->generateToken($user);
        $result = $this->service->register($tokenResult['token'], '999888777');

        $this->assertTrue($result['success']);
        $this->assertSame('999888777', $user->fresh()->telegram_chat_id);
    }

    public function test_marks_token_as_used_after_registration(): void
    {
        $client = ClientFactory::new()->create();
        $user = UserFactory::new()->create(['client_id' => $client->id, 'telegram_chat_id' => null]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $tokenResult = $this->service->generateToken($user);
        $this->service->register($tokenResult['token'], '111222333');

        $record = TelegramRegistrationToken::query()
            ->where('token', $tokenResult['token'])
            ->first();

        $this->assertTrue($record->isUsed());
    }

    public function test_rejects_invalid_token(): void
    {
        $result = $this->service->register('DOESNOTEXIST', '555666777');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('tidak valid', $result['message']);
    }

    public function test_rejects_already_used_token(): void
    {
        $client = ClientFactory::new()->create();
        $user = UserFactory::new()->create(['client_id' => $client->id, 'telegram_chat_id' => null]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $tokenResult = $this->service->generateToken($user);
        $this->service->register($tokenResult['token'], '111111111');

        // Second attempt with same token should fail
        $result = $this->service->register($tokenResult['token'], '222222222');

        $this->assertFalse($result['success']);
    }

    public function test_rejects_if_chat_id_already_belongs_to_another_user(): void
    {
        $client = ClientFactory::new()->create();

        $existingUser = UserFactory::new()->create([
            'client_id' => $client->id,
            'telegram_chat_id' => '444444444',
        ]);
        $existingUser->assignRole(UserRole::CLIENT_MAKLON->value);

        $newUser = UserFactory::new()->create(['client_id' => $client->id, 'telegram_chat_id' => null]);
        $newUser->assignRole(UserRole::CLIENT_MAKLON->value);

        $tokenResult = $this->service->generateToken($newUser);
        $result = $this->service->register($tokenResult['token'], '444444444');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('pengguna lain', $result['message']);
    }

    public function test_rejects_if_user_already_linked_to_different_chat_id(): void
    {
        $client = ClientFactory::new()->create();
        $user = UserFactory::new()->create([
            'client_id' => $client->id,
            'telegram_chat_id' => '777777777',
        ]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $tokenResult = $this->service->generateToken($user);
        $result = $this->service->register($tokenResult['token'], '888888888');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Telegram yang berbeda', $result['message']);
    }

    public function test_rejects_non_client_maklon_user(): void
    {
        $user = UserFactory::new()->create(['client_id' => null]);
        $user->assignRole(UserRole::WAREHOUSE->value);

        $tokenResult = $this->service->generateToken($user);
        $result = $this->service->register($tokenResult['token'], '123456789');

        $this->assertFalse($result['success']);
    }
}
