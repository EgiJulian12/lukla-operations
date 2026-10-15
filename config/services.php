<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Amazon Bedrock RAG Configuration
    |--------------------------------------------------------------------------
    |
    | Native Laravel RAG implementation using the AWS SDK. Documents are
    | loaded from S3 into Laravel's cache, then relevant chunks are injected
    | into the prompt before calling the Bedrock Converse API.
    |
    */

    'bedrock' => [
        // AWS credentials (shared with the rest of the app)
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),

        // S3 bucket is in ap-southeast-1; Bedrock Converse runs in us-east-1
        's3_region' => env('AWS_DEFAULT_REGION', 'ap-southeast-1'),
        'bedrock_region' => env('BEDROCK_REGION', 'us-east-1'),
        'bucket' => env('AWS_BUCKET'),

        // Model and inference settings
        'model_id' => env('BEDROCK_MODEL_ID', 'amazon.nova-lite-v1:0'),
        'max_tokens' => (int) env('BEDROCK_MAX_TOKENS', 4000),
        'temperature' => (float) env('BEDROCK_TEMPERATURE', 0.1),

        // RAG retrieval settings
        'top_k' => (int) env('RAG_TOP_K', 5),
        'chunk_size' => (int) env('RAG_CHUNK_SIZE', 600),
        'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 100),

        // How long to keep loaded document chunks in Laravel cache (seconds)
        'cache_ttl' => (int) env('RAG_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    |
    | Used by the Telegram webhook endpoint to validate incoming requests and
    | to send reply messages back to clients via the Bot API.
    |
    */

    'telegram' => [
        // Secret token set when registering the webhook with Telegram (setWebhook).
        // Telegram includes this in the X-Telegram-Bot-Api-Secret-Token header.
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

        // Bot token issued by @BotFather.
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),

        // Bot @username shown in the registration instructions modal.
        'bot_username' => env('TELEGRAM_BOT_USERNAME', 'LuklaOperationsBot'),

        // Telegram Bot API base URL (override for local proxies if needed).
        'api_url' => env('TELEGRAM_API_URL', 'https://api.telegram.org'),

        // TLS certificate verification for outbound Bot API calls. Dev machines
        // behind a TLS-inspecting proxy or antivirus (self-signed cert in the
        // chain) must set TELEGRAM_VERIFY_SSL=false, otherwise every reply
        // silently fails. Leave enabled in production.
        'verify_ssl' => env('TELEGRAM_VERIFY_SSL', true),
    ],

];
