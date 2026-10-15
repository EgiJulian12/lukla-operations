<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the X-Telegram-Bot-Api-Secret-Token header on every incoming
 * Telegram webhook request (Telegram Bot API docs — setWebhook secret_token).
 *
 * Returns 403 JSON if the header is absent or does not match the configured
 * secret. Uses hash_equals() to prevent timing-attack comparison.
 */
class VerifyTelegramSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredSecret = config('services.telegram.webhook_secret');

        $incomingToken = $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if (
            empty($configuredSecret)
            || ! hash_equals((string) $configuredSecret, (string) $incomingToken)
        ) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
