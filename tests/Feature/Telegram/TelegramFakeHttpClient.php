<?php

namespace Tests\Feature\Telegram;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Telegram\Bot\HttpClients\HttpClientInterface;

/**
 * Pengganti Guzzle untuk SDK Telegram: tidak ada permintaan keluar nyata selama
 * test, tetapi setiap payload yang "terkirim" terekam untuk asersi.
 * Diletakkan pada config telegram.http_client_handler sebagai instance.
 */
final class TelegramFakeHttpClient implements HttpClientInterface
{
    /** @var array<int, array{url: string, body: ?string}> */
    public static array $sent = [];

    public static function reset(): void
    {
        self::$sent = [];
    }

    public function send(
        string $url,
        string $method,
        array $headers = [],
        array $options = [],
        bool $isAsyncRequest = false,
    ): ResponseInterface {
        self::$sent[] = [
            'url' => $url,
            'method' => $method,
            'form_params' => $options['form_params'] ?? null,
            'query' => $options['query'] ?? null,
        ];

        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'ok' => true,
            'result' => [
                'update_id' => 0,
                'message_id' => count(self::$sent),
                'date' => time(),
                'chat' => ['id' => 1, 'type' => 'private'],
                'text' => '',
            ],
        ]));
    }

    public function getTimeOut(): int
    {
        return 1;
    }

    public function setTimeOut(int $timeOut): static
    {
        return $this;
    }

    public function getConnectTimeOut(): int
    {
        return 1;
    }

    public function setConnectTimeOut(int $connectTimeOut): static
    {
        return $this;
    }
}
