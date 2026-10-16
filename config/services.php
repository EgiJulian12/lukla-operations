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
    | Owner Telegram Chatbot & Bedrock Assistant
    |--------------------------------------------------------------------------
    |
    | Configuration for the Owner-only API and Bedrock AI assistant used
    | by the Telegram chatbot to track inventory items, client billing,
    | orders, and operational KPIs.
    |
    */
    'owner_bot' => [
        'api_key' => env('OWNER_BOT_API_KEY', env('TELEGRAM_BOT_SECRET', 'lukla-owner-secret-key')),
        'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'allowed_telegram_user_ids' => array_values(array_filter(explode(',', (string) env('TELEGRAM_OWNER_USER_IDS', '')))),
        'model_id' => env('BEDROCK_OWNER_MODEL_ID', env('BEDROCK_MODEL_ID', 'amazon.nova-lite-v1:0')),
        'max_tokens' => (int) env('BEDROCK_OWNER_MAX_TOKENS', 1500),
        'temperature' => (float) env('BEDROCK_OWNER_TEMPERATURE', 0.2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Telegram Bedrock Assistant
    |--------------------------------------------------------------------------
    |
    | Inference settings for the agentic AI assistant on the client bot.
    | AWS credentials and region are shared with the `bedrock` block above;
    | rows are always scoped to the client in ClientDataService.
    |
    */
    'client_bot' => [
        'model_id' => env('BEDROCK_CLIENT_MODEL_ID', env('BEDROCK_MODEL_ID', 'amazon.nova-lite-v1:0')),
        'max_tokens' => (int) env('BEDROCK_CLIENT_MAX_TOKENS', 1500),
        'temperature' => (float) env('BEDROCK_CLIENT_TEMPERATURE', 0.2),
    ],

];
