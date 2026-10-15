<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\Client;
use App\Services\Ai\TelegramUserResolver;
use Database\Factories\ClientFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TelegramUserResolverTest extends TestCase
{
    use RefreshDatabase;

    private TelegramUserResolver $resolver;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new TelegramUserResolver;

        // Create a real client so the users FK constraint is satisfied.
        $this->client = ClientFactory::new()->create();

        // Ensure roles exist before assigning them.
        Role::firstOrCreate(['name' => UserRole::CLIENT_MAKLON->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => UserRole::WAREHOUSE->value, 'guard_name' => 'web']);
    }

    public function test_returns_null_when_chat_id_is_not_linked(): void
    {
        $result = $this->resolver->resolve('99999999');

        $this->assertNull($result);
    }

    public function test_returns_null_when_user_is_inactive(): void
    {
        $user = UserFactory::new()->create([
            'telegram_chat_id' => '111111',
            'is_active' => false,
            'client_id' => $this->client->id,
        ]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $result = $this->resolver->resolve('111111');

        $this->assertNull($result);
    }

    public function test_returns_null_when_user_does_not_have_client_maklon_role(): void
    {
        $user = UserFactory::new()->create([
            'telegram_chat_id' => '222222',
            'is_active' => true,
            'client_id' => $this->client->id,
        ]);
        $user->assignRole(UserRole::WAREHOUSE->value);

        $result = $this->resolver->resolve('222222');

        $this->assertNull($result);
    }

    public function test_returns_null_when_user_has_no_client_id(): void
    {
        $user = UserFactory::new()->create([
            'telegram_chat_id' => '333333',
            'is_active' => true,
            'client_id' => null,
        ]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $result = $this->resolver->resolve('333333');

        $this->assertNull($result);
    }

    public function test_returns_user_when_all_conditions_are_met(): void
    {
        $user = UserFactory::new()->create([
            'telegram_chat_id' => '444444',
            'is_active' => true,
            'client_id' => $this->client->id,
        ]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $result = $this->resolver->resolve('444444');

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
    }

    public function test_accepts_integer_chat_id(): void
    {
        $user = UserFactory::new()->create([
            'telegram_chat_id' => '555555',
            'is_active' => true,
            'client_id' => $this->client->id,
        ]);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        $result = $this->resolver->resolve(555555);

        $this->assertNotNull($result);
        $this->assertSame($user->id, $result->id);
    }
}
