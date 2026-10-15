<?php

namespace App\Http\Controllers;

use App\Services\Ai\DatabaseQueryService;
use App\Services\Ai\IntentRouter;
use App\Services\Ai\RagService;
use App\Services\Ai\TelegramRagService;
use App\Services\Ai\TelegramUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Direct test endpoint for the bot query pipeline.
 *
 * Bypasses Telegram entirely — accepts a plain JSON body and returns the
 * full pipeline response so the intent router, database queries, and Bedrock
 * answer can be inspected without a live Telegram setup.
 *
 * NOT intended for production use. Guard it with APP_ENV=local or remove
 * the route before deploying.
 *
 * Request body:
 *   {
 *     "query":   "Lihat pesanan saya",   // required
 *     "chat_id": "123456789"             // optional — skips client auth when omitted
 *   }
 *
 * Response:
 *   {
 *     "query":    "...",
 *     "intent":   { "intent": "db", "model": "orders", "filters": {} },
 *     "client_id": 1,
 *     "db_context": { "model": "orders", "count": 2, "data": [...] },
 *     "answer":   "...",
 *     "sources":  [...],
 *     "model_id": "amazon.nova-lite-v1:0"
 *   }
 */
class BotQueryController extends Controller
{
    public function __construct(
        private readonly TelegramUserResolver $userResolver,
        private readonly IntentRouter $intentRouter,
        private readonly DatabaseQueryService $databaseQueryService,
        private readonly TelegramRagService $telegramRagService,
        private readonly RagService $ragService,
    ) {}

    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'min:1', 'max:1000'],
            'chat_id' => ['nullable', 'string'],
        ]);

        $queryText = trim($request->string('query')->toString());
        $chatId = $request->input('chat_id');

        // --- Resolve client (optional) ---
        $user = null;
        $clientId = null;

        if ($chatId !== null) {
            $user = $this->userResolver->resolve($chatId);

            if ($user === null) {
                return response()->json([
                    'error' => 'chat_id not linked to an active CLIENT_MAKLON account.',
                    'hint' => 'Set telegram_chat_id on a CLIENT_MAKLON user, or omit chat_id to test without auth.',
                ], 422);
            }

            $clientId = (int) $user->client_id;
        }

        // --- Detect intent ---
        $intent = $this->intentRouter->detectIntent($queryText);

        Log::info('BotQueryController: test query.', [
            'query' => $queryText,
            'intent' => $intent,
            'client_id' => $clientId,
        ]);

        // --- Route and build response ---
        $dbContext = null;
        $s3Chunks = [];
        $ragResult = null;

        match ($intent['intent']) {
            'db' => $this->handleDb($queryText, $clientId, $intent, $dbContext, $ragResult),
            'hybrid' => $this->handleHybrid($queryText, $clientId, $intent, $dbContext, $s3Chunks, $ragResult),
            default => $this->handleDoc($queryText, $ragResult),
        };

        return response()->json([
            'query' => $queryText,
            'intent' => $intent,
            'client_id' => $clientId,
            'db_context' => $dbContext,
            's3_chunks_used' => count($s3Chunks),
            'answer' => $ragResult['answer'] ?? null,
            'sources' => $ragResult['sources'] ?? [],
            'model_id' => $ragResult['model_id'] ?? null,
        ]);
    }

    // -----------------------------------------------------------------------
    // Private helpers (mirrors the controller logic, but collects debug data)
    // -----------------------------------------------------------------------

    /**
     * @param  array{intent: string, model: string|null, filters: array<string, mixed>}  $intent
     * @param  array{model: string, count: int, data: list<array<string, mixed>>}|null  $dbContext
     * @param  array{answer: string, sources: list<array<string, mixed>>, model_id: string}|null  $ragResult
     */
    private function handleDb(
        string $query,
        ?int $clientId,
        array $intent,
        ?array &$dbContext,
        ?array &$ragResult,
    ): void {
        if ($intent['model'] === null || $clientId === null) {
            $this->handleDoc($query, $ragResult);

            return;
        }

        $dbContext = $this->databaseQueryService->query(
            $intent['model'],
            $clientId,
            $intent['filters'],
        );

        $ragResult = $this->telegramRagService->ask($query, $dbContext);
    }

    /**
     * @param  array{intent: string, model: string|null, filters: array<string, mixed>}  $intent
     * @param  array{model: string, count: int, data: list<array<string, mixed>>}|null  $dbContext
     * @param  list<array{content: string, source: string, chunk_index: int, score: float}>  $s3Chunks
     * @param  array{answer: string, sources: list<array<string, mixed>>, model_id: string}|null  $ragResult
     */
    private function handleHybrid(
        string $query,
        ?int $clientId,
        array $intent,
        ?array &$dbContext,
        array &$s3Chunks,
        ?array &$ragResult,
    ): void {
        if ($intent['model'] !== null && $clientId !== null) {
            $dbContext = $this->databaseQueryService->query(
                $intent['model'],
                $clientId,
                $intent['filters'],
            );
        }

        $s3Chunks = $this->ragService->getRelevantChunksForQuery($query);

        $ragResult = $this->telegramRagService->ask($query, $dbContext ?? [], $s3Chunks);
    }

    /**
     * @param  array{answer: string, sources: list<array<string, mixed>>, model_id: string, session_id: string}|null  $ragResult
     */
    private function handleDoc(string $query, ?array &$ragResult): void
    {
        $ragResult = $this->ragService->askQuestion($query);
    }
}
