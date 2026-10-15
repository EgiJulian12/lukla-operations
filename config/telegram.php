<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bots (Owner + Client)
    |--------------------------------------------------------------------------
    |
    | Dua bot: owner (staff internal) dan client (klien maklon).
    | webhook_secret dipakai untuk validasi header
    | X-Telegram-Bot-Api-Secret-Token serta segmen {secret} pada route webhook.
    | username dipakai untuk membuat deep link https://t.me/<username>?start=<token>.
    |
    */
    'bots' => [
        'owner' => [
            'token' => env('TELEGRAM_OWNER_BOT_TOKEN', ''),
            'username' => env('TELEGRAM_OWNER_BOT_USERNAME', ''),
            'webhook_secret' => env('TELEGRAM_OWNER_WEBHOOK_SECRET', ''),
            'allowed_updates' => null,
        ],

        'client' => [
            'token' => env('TELEGRAM_CLIENT_BOT_TOKEN', ''),
            'username' => env('TELEGRAM_CLIENT_BOT_USERNAME', ''),
            'webhook_secret' => env('TELEGRAM_CLIENT_WEBHOOK_SECRET', ''),
            'allowed_updates' => null,
        ],
    ],

    'default' => 'client',

    // Base URL publik (HTTPS) yang dipanggil Telegram untuk kirim update webhook.
    'app_url' => env('TELEGRAM_APP_URL', env('APP_URL')),

    'async_requests' => false,

    'http_client_handler' => null,

    'base_bot_url' => null,
];
