<?php

use App\Http\Controllers\Telegram\ClientBotWebhookController;
use App\Http\Controllers\Telegram\OwnerBotWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/owner/{secret}', OwnerBotWebhookController::class)
    ->name('telegram.owner.webhook');

Route::post('/telegram/client/{secret}', ClientBotWebhookController::class)
    ->name('telegram.client.webhook');
