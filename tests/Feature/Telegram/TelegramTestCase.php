<?php

namespace Tests\Feature\Telegram;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Fondasi bagi seluruh test webhook Telegram: konfigurasi dua bot dengan
 * token, secret, dan HTTP client palsu (tanpa akses jaringan), plus fixture
 * update Telegram dan pembuatan user Client/Staf.
 */
abstract class TelegramTestCase extends TestCase
{
    use RefreshDatabase;

    protected string $clientSecret = 'secret-client-tests';

    protected string $ownerSecret = 'secret-owner-tests';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        TelegramFakeHttpClient::reset();

        foreach (['client', 'owner'] as $bot) {
            config()->set('telegram.bots.'.$bot, [
                'token' => '100000:TEST-TOKEN-'.$bot,
                'username' => ' lukla_'.$bot.'_test_bot ',
                'webhook_secret' => $bot === 'client' ? $this->clientSecret : $this->ownerSecret,
                'allowed_updates' => null,
            ]);
        }

        config()->set('telegram.http_client_handler', new TelegramFakeHttpClient);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function clientUser(array $attributes = []): User
    {
        $user = User::factory()->for(Client::factory(), 'client')->create($attributes);
        $user->assignRole(UserRole::CLIENT_MAKLON->value);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function internalUser(UserRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    protected function postUpdate(string $bot, array $update, ?string $secret = null): TestResponse
    {
        $secret ??= $this->{$bot.'Secret'};

        return $this->postJson(
            "/telegram/{$bot}/{$secret}",
            $update,
            ['X-Telegram-Bot-Api-Secret-Token' => $secret],
        );
    }

    /**
     * Update pesan chat pribadi.
     *
     * @return array<string, mixed>
     */
    protected function messageUpdate(string $text, int $telegramUserId = 7_001, ?int $chatId = null): array
    {
        return [
            'update_id' => random_int(1, 999_999),
            'message' => [
                'message_id' => 10,
                'from' => ['id' => $telegramUserId, 'first_name' => 'A', 'username' => 'user'],
                'chat' => ['id' => $chatId ?? $telegramUserId, 'type' => 'private'],
                'date' => now()->getTimestamp(),
                'text' => $text,
            ],
        ];
    }

    /**
     * Update callback query tombol inline (chat pribadi).
     *
     * @return array<string, mixed>
     */
    protected function callbackUpdate(string $data, int $telegramUserId): array
    {
        return [
            'update_id' => random_int(1, 999_999),
            'callback_query' => [
                'id' => (string) random_int(1, 999_999),
                'from' => ['id' => $telegramUserId],
                'message' => [
                    'message_id' => 5,
                    'chat' => ['id' => $telegramUserId, 'type' => 'private'],
                    'date' => now()->getTimestamp(),
                ],
                'data' => $data,
            ],
        ];
    }

    /**
     * Pesan yang dikirim oleh aplikasi ke metode sendMessage (sudah di-decode).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sentMessages(): array
    {
        return collect(TelegramFakeHttpClient::$sent)
            ->filter(fn (array $item): bool => str_contains($item['url'], '/sendMessage'))
            ->map(fn (array $item): array => $item['form_params'] ?? [])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function sentTexts(): array
    {
        return collect($this->sentMessages())
            ->map(fn (array $message): string => (string) ($message['text'] ?? ''))
            ->values()
            ->all();
    }
}
