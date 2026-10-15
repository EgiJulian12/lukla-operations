<?php

use App\Http\Controllers\Api\OwnerBotController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Owner Bot API Routes
|--------------------------------------------------------------------------
|
| Exclusively accessible by the Owner / Eksekutif Internal role and secured
| via Owner Bot authentication. Connects directly to Amazon Bedrock and the
| Lukla Operations database for Telegram chatbot integrations.
|
*/

Route::middleware(['owner.bot'])->prefix('owner')->group(function (): void {
    // Conversational Chatbot endpoint (Bedrock AI + DB Tools)
    Route::match(['get', 'post'], '/chat', [OwnerBotController::class, 'chat'])->name('api.owner.chat');

    // Direct structured query endpoints
    Route::get('/inventory/track/{identifier}', [OwnerBotController::class, 'trackInventory'])->name('api.owner.inventory.track');
    Route::get('/clients/{identifier}/billing', [OwnerBotController::class, 'clientBilling'])->name('api.owner.clients.billing');
    Route::get('/orders/{identifier}/status', [OwnerBotController::class, 'orderStatus'])->name('api.owner.orders.status');
    Route::get('/overview', [OwnerBotController::class, 'overview'])->name('api.owner.overview');

    // Telegram Bot Webhook endpoint
    Route::post('/telegram/webhook', [OwnerBotController::class, 'telegramWebhook'])->name('api.owner.telegram.webhook');
});

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
