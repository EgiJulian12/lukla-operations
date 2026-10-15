<?php

use App\Http\Middleware\VerifyTelegramSignature;
use App\Http\Middleware\EnsureOwnerBotAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Route webhook Telegram: tanpa middleware web/session, validasi
            // identitas Telegram dilakukan oleh segmen {secret} + header
            // X-Telegram-Bot-Api-Secret-Token di dalam controller.
            Route::group([], __DIR__.'/../routes/telegram.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'telegram.webhook' => VerifyTelegramSignature::class,
            'owner.bot' => EnsureOwnerBotAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
