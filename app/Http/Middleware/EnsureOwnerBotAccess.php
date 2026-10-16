<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce that API requests come strictly from an authenticated Owner Bot
 * or an authenticated user with the OWNER_EXECUTIVE role.
 */
class EnsureOwnerBotAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = config('services.owner_bot.api_key');

        // 1. Check Bearer Token
        $bearerToken = $request->bearerToken();
        if ($bearerToken && hash_equals((string) $expectedKey, (string) $bearerToken)) {
            return $this->validateTelegramUser($request, $next);
        }

        // 2. Check X-Bot-Token Header
        $customHeader = $request->header('X-Bot-Token');
        if ($customHeader && hash_equals((string) $expectedKey, (string) $customHeader)) {
            return $this->validateTelegramUser($request, $next);
        }

        // 3. Check query/body api_key
        $paramKey = $request->input('api_key');
        if ($paramKey && hash_equals((string) $expectedKey, (string) $paramKey)) {
            return $this->validateTelegramUser($request, $next);
        }

        // 4. Check if authenticated as a User with Owner role
        $user = $request->user();
        if ($user && $user->hasRole(UserRole::OWNER_EXECUTIVE->value)) {
            return $next($request);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Akses ditolak. API ini hanya diperuntukkan bagi role Owner / Eksekutif Internal.',
        ], 401);
    }

    /**
     * Optional validation for Telegram User ID if configured.
     */
    private function validateTelegramUser(Request $request, Closure $next): Response
    {
        $allowedUserIds = config('services.owner_bot.allowed_telegram_user_ids', []);

        if (! empty($allowedUserIds)) {
            $senderId = (string) ($request->input('telegram_user_id')
                ?? $request->input('chat_id')
                ?? $request->input('message.from.id')
                ?? $request->input('message.chat.id')
                ?? '');

            if ($senderId !== '' && ! in_array($senderId, $allowedUserIds, true)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => "Akses ditolak. Telegram User ID '{$senderId}' tidak terdaftar sebagai Owner yang berwenang.",
                ], 403);
            }
        }

        return $next($request);
    }
}
