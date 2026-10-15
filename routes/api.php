<?php

use App\Http\Controllers\BotQueryController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the application's route configuration in
| bootstrap/app.php and are automatically prefixed with /api.
|
*/

Route::post('/client/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->middleware('telegram.webhook')
    ->name('telegram.webhook');
