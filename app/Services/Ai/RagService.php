<?php

namespace App\Services\Ai;

use App\Helpers\MarkdownToText;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Native PHP RAG service — no external Python process required.
 *
 * Flow for askQuestion():
 *  1. Load document chunks from S3DocumentLoader (cached in Laravel cache).
 *  2. Score each chunk against the query with keyword overlap (TF-style).
 *  3. Inject the top-k chunks as context into the prompt.
 *  4. Call the Amazon Bedrock Converse API and return the answer.
 */
class RagService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an AI assistant for Lukla Operations Engine, a manufacturing operations
platform for garment production. You have access to the system's business
requirements, process flows, and operational documentation.

IMPORTANT RULES:
1. You MUST ONLY answer based on the provided context. Do NOT use your own knowledge.
2. If the context does not contain relevant information, respond with:
   "Maaf, informasi tentang [pertanyaan Anda] tidak tersedia dalam dokumentasi. Silakan hubungi admin untuk pertanyaan lebih lanjut."
3. Answer in the same language as the question (Indonesian or English).
4. Format your answer with clear paragraphs and bullet points, but write in plain text:
   - Use blank lines between paragraphs
   - Use bullets (•) for lists
   - Do NOT use any markdown syntax like **bold**, *, _, #, or ---
   - Do NOT include any information not explicitly in the context
5. Always cite your source(s) at the end of your answer

PROMPT;

    private BedrockRuntimeClient $bedrock;

    private S3Client $s3;

    private S3DocumentLoader $loader;

    private string $bucket;

    private string $modelId;

    private int $maxTokens;

    private float $temperature;

    private int $topK;

    public function __construct(S3DocumentLoader $loader)
    {
        $cfg = config('services.bedrock');

        $this->loader = $loader;
        $this->bucket = $cfg['bucket'];
        $this->modelId = $cfg['model_id'];
        $this->maxTokens = $cfg['max_tokens'];
        $this->temperature = $cfg['temperature'];
        $this->topK = $cfg['top_k'];

        $credentials = [
            'key' => $cfg['key'],
            'secret' => $cfg['secret'],
        ];

        $this->bedrock = new BedrockRuntimeClient([
            'version' => 'latest',
            'region' => $cfg['bedrock_region'],
            'credentials' => $credentials,
            'http' => [
                'verify' => false,  // Bypass SSL cert verification for dev
            ],
        ]);

        $this->s3 = new S3Client([
            'version' => 'latest',
            'region' => $cfg['s3_region'],
            'credentials' => $credentials,
        ]);
    }

    /**
     * Ask a question, optionally enriched with portal client context.
     *
     * @param  array<string, mixed>  $context  Client context passed from the portal widget.
     * @return array{answer: string, sources: list<array<string, mixed>>, model_id: string, session_id: string}
     *
     * @throws \Exception
     */
    public function askQuestion(string $query, string $mode = 'generate_only', array $context = []): array
    {
        $enrichedQuery = $this->enrichQueryWithContext($query, $context);

        $chunks = $this->retrieveRelevantChunks($enrichedQuery);

        [$prompt, $sources] = $this->buildPromptWithContext($enrichedQuery, $chunks);

        $answer = MarkdownToText::convert($this->invokeModel($prompt));

        return [
            'answer' => $answer,
            'sources' => $sources,
            'model_id' => $this->modelId,
            'session_id' => (string) Str::uuid(),
        ];
    }

    /**
     * Returns a health status array.
     * Never throws — used by monitoring / the portal widget.
     *
     * @return array{status: string, message?: string}
     */
    public function getHealthStatus(): array
    {
        try {
            // Light-weight check: just verify Bedrock accepts a tiny request.
            $this->bedrock->converse([
                'modelId' => $this->modelId,
                'messages' => [['role' => 'user', 'content' => [['text' => 'ping']]]],
                'inferenceConfig' => ['maxTokens' => 1],
            ]);

            return ['status' => 'healthy', 'model_id' => $this->modelId];
        } catch (\Throwable $e) {
            Log::warning('RagService health check failed.', ['error' => $e->getMessage()]);

            return ['status' => 'degraded', 'message' => $e->getMessage()];
        }
    }

    /**
     * Search documentation chunks relevant to a query.
     *
     * @return list<array{content: string, source: string, chunk_index: int, score: float}>
     */
    public function searchDocuments(string $query, ?int $limit = null): array
    {
        $chunks = $this->retrieveRelevantChunks($query);

        if ($limit !== null) {
            return array_slice($chunks, 0, $limit);
        }

        return $chunks;
    }

    /**
     * Upload a file to S3 and invalidate the document cache so new content

     * is available on the next question.
     *
     * @return array{success: bool, message: string, s3_key?: string, file_name: string}
     *
     * @throws \Exception
     */
    public function uploadDocument(UploadedFile $file): array
    {
        $s3Key = 'documents/'.$file->getClientOriginalName();

        $this->s3->putObject([
            'Bucket' => $this->bucket,
            'Key' => $s3Key,
            'Body' => $file->getContent(),
            'ContentType' => $file->getMimeType() ?? 'application/octet-stream',
        ]);

        // Bust the chunk cache so the new document is indexed immediately.
        $this->loader->reload();

        Log::info('RagService: document uploaded.', ['key' => $s3Key]);

        return [
            'success' => true,
            'message' => 'Document uploaded successfully.',
            's3_key' => $s3Key,
            'file_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Returns an empty array — model listing is not available for this
     * AWS account (ListFoundationModels is denied by IAM policy).
     *
     * @return array<int, mixed>
     */
    public function getAvailableModels(): array
    {
        return [];
    }

    /**
     * Public proxy for retrieving scored S3 chunks for a given query.
     *
     * Exposed so that external services (e.g. TelegramRagService) can retrieve
     * relevant documentation chunks without duplicating the scoring logic.
     *
     * @return list<array{content: string, source: string, chunk_index: int, score: float}>
     */
    public function getRelevantChunksForQuery(string $query): array
    {
        return $this->retrieveRelevantChunks($query);
    }

    /**
     * Quick liveness check used by the portal widget badge.
     */
    public function isAvailable(): bool
    {
        $health = $this->getHealthStatus();

        return $health['status'] === 'healthy';
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * When portal client context is provided, prepend a brief description
     * so the model can give personalised answers.
     */
    private function enrichQueryWithContext(string $query, array $context): string
    {
        if (empty($context)) {
            return $query;
        }

        $lines = [];

        if (isset($context['client_name'])) {
            $lines[] = "Client: {$context['client_name']}";
        }
        if (isset($context['active_orders'])) {
            $lines[] = "Active orders: {$context['active_orders']}";
        }
        if (isset($context['client_type'])) {
            $lines[] = "Client type: {$context['client_type']}";
        }

        if (empty($lines)) {
            return $query;
        }

        $contextHeader = implode(' | ', $lines);

        return "[Context: {$contextHeader}]\n{$query}";
    }

    /**
     * Score every cached chunk against the query with keyword overlap,
     * applying a 1.5× boost for chunks whose opening line looks like a
     * Markdown heading (mirrors the Python scoring).
     *
     * @return list<array{content: string, source: string, chunk_index: int, score: float}>
     */
    private function retrieveRelevantChunks(string $query): array
    {
        $allChunks = $this->loader->getChunks();

        if (empty($allChunks)) {
            return [];
        }

        preg_match_all('/\b\w{3,}\b/u', mb_strtolower($query), $qm);
        $queryTerms = array_flip($qm[0]);   // set-like: O(1) lookup

        $scored = [];

        foreach ($allChunks as $s3Key => $chunks) {
            foreach ($chunks as $idx => $chunk) {
                preg_match_all('/\b\w{3,}\b/u', mb_strtolower($chunk), $cm);
                $chunkTerms = array_unique($cm[0]);

                $overlap = 0;
                foreach ($chunkTerms as $term) {
                    if (isset($queryTerms[$term])) {
                        $overlap++;
                    }
                }

                if ($overlap === 0) {
                    continue;
                }

                // Boost chunks that start with a Markdown heading.
                $headerBoost = preg_match('/^#+\s/m', substr($chunk, 0, 50)) ? 1.5 : 1.0;
                $score = $overlap * $headerBoost;

                $scored[] = [
                    'content' => $chunk,
                    'source' => $s3Key,
                    'chunk_index' => $idx,
                    'score' => round($score, 2),
                ];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $this->topK);
    }

    /**
     * Build the final prompt string with context injected and return the
     * source list for the response payload.
     *
     * @param  list<array{content: string, source: string, chunk_index: int, score: float}>  $chunks
     * @return array{0: string, 1: list<array<string, mixed>>}
     */
    private function buildPromptWithContext(string $query, array $chunks): array
    {
        if (empty($chunks)) {
            Log::debug('RagService: no matching chunks — using model general knowledge.');

            return [$query, []];
        }

        $contextParts = [];
        $sources = [];

        foreach ($chunks as $i => $chunk) {
            $n = $i + 1;
            $contextParts[] = "[Source {$n} — {$chunk['source']}]\n{$chunk['content']}";
            $sources[] = [
                'source' => $chunk['source'],
                'chunk_index' => $chunk['chunk_index'],
                'score' => $chunk['score'],
                'uri' => "s3://{$this->bucket}/{$chunk['source']}",
            ];
        }

        $contextBlock = implode("\n\n---\n\n", $contextParts);

        $prompt = <<<TEXT
        Use the following context from the Lukla Operations documentation to answer the question.

        === CONTEXT ===
        {$contextBlock}
        === END CONTEXT ===

        Question: {$query}
        TEXT;

        Log::debug('RagService: built prompt with document context.', [
            'chunks' => count($chunks),
            'sources' => array_column($sources, 'source'),
        ]);

        return [$prompt, $sources];
    }

    /**
     * Call the Bedrock Converse API and return the text response.
     *
     * @throws \Exception
     */
    private function invokeModel(string $prompt): string
    {
        $result = $this->bedrock->converse([
            'modelId' => $this->modelId,
            'messages' => [
                ['role' => 'user', 'content' => [['text' => $prompt]]],
            ],
            'system' => [
                ['text' => self::SYSTEM_PROMPT],
            ],
            'inferenceConfig' => [
                'maxTokens' => $this->maxTokens,
                'temperature' => $this->temperature,
            ],
        ]);

        return $result['output']['message']['content'][0]['text'];
    }
}
