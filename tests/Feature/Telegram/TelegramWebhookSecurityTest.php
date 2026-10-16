<?php

namespace Tests\Feature\Telegram;

use Illuminate\Testing\TestResponse;

/**
 * Pintu webhook: segmen {secret} dan header X-Telegram-Bot-Api-Secret-Token
 * wajib benar-duanya; salah satu saja tidak boleh sampai ke router.
 */
final class TelegramWebhookSecurityTest extends TelegramTestCase
{
    public function test_correct_secret_and_header_reaches_the_router(): void
    {
        $this->postBot($this->messageUpdate('/help'))->assertOk();
        $this->assertCount(1, $this->sentTexts(), 'Bot membalas sekali untuk /help.');
    }

    public function test_missing_header_is_rejected(): void
    {
        $this->postBot($this->messageUpdate('/start'), withHeader: false)->assertStatus(403);

        $this->assertSame([], $this->sentTexts());
    }

    public function test_wrong_header_value_is_rejected(): void
    {
        $this->postBot($this->messageUpdate('/start'), header: 'not-the-real-secret-value')->assertStatus(403);

        $this->assertSame([], $this->sentTexts());
    }

    public function test_wrong_url_secret_is_rejected(): void
    {
        $this->postBot($this->messageUpdate('/start'), secret: 'not-the-url-secret')->assertStatus(403);

        $this->assertSame([], $this->sentTexts());
    }

    public function test_owner_bot_secret_is_not_accepted_on_client_route(): void
    {
        $this->postBot($this->messageUpdate('/start'), secret: $this->ownerSecret, header: $this->ownerSecret)
            ->assertStatus(403);

        $this->assertSame([], $this->sentTexts());
    }

    public function test_unknown_bot_name_is_not_routed(): void
    {
        $this->postBot($this->messageUpdate('/start'), pathBot: 'xyz')->assertNotFound();

        $this->assertSame([], $this->sentTexts());
    }

    /**
     * @param  array<string, mixed>  $update
     */
    private function postBot(
        array $update,
        ?string $secret = null,
        bool $withHeader = true,
        ?string $header = null,
        string $pathBot = 'client',
    ): TestResponse {
        $secret ??= $this->clientSecret;
        $header ??= $secret;

        return $this->postJson(
            "/telegram/{$pathBot}/{$secret}",
            $update,
            $withHeader ? ['X-Telegram-Bot-Api-Secret-Token' => $header] : [],
        );
    }
}
