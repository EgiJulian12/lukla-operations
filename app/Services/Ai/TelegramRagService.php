<?php

namespace App\Services\Ai;

use App\Helpers\MarkdownToText;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Dedicated Bedrock service for the Telegram bot.
 *
 * Responsibilities:
 *  - Accepts live database context (from DatabaseQueryService) and optional
 *    S3 RAG chunks (for hybrid intent), then builds a single prompt.
 *  - Keeps a client-facing system prompt — answers are framed for a garment
 *    factory client, not internal staff.
 *  - Leaves the existing RagService completely untouched; that service
 *    continues to serve the portal chat widget as before.
 *
 * Intent routing:
 *  - "db"     → buildDbOnlyPrompt()    — DB data only, no S3 chunks
 *  - "hybrid" → buildHybridPrompt()    — DB data + S3 documentation chunks
 *  - (doc intent is handled by the original RagService, not this class)
 */
class TelegramRagService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda adalah asisten AI untuk klien Lukla Operations Engine, sebuah platform
manajemen produksi garmen. Anda membantu klien memahami status pesanan,
material, inventori, output produksi, dan tarif jahit mereka.

ATURAN PENTING:
1. Jawab HANYA berdasarkan data yang disediakan dalam konteks di bawah ini.
   Jangan gunakan pengetahuan umum Anda sendiri.
2. Jika konteks tidak memuat informasi yang relevan, balas:
   "Maaf, data yang Anda cari tidak tersedia saat ini. Silakan hubungi tim Lukla untuk informasi lebih lanjut."
3. Jawab dalam bahasa yang sama dengan pertanyaan (Indonesia atau Inggris).
4. Format jawaban Anda dalam teks biasa (plain text):
   - Gunakan baris kosong antar paragraf
   - Gunakan bullet (•) untuk daftar
   - JANGAN gunakan sintaks Markdown seperti **bold**, *, _, #, atau ---
   - JANGAN sertakan informasi yang tidak ada dalam konteks
5. DATA PESANAN ANDA adalah sumber data nyata dan terkini — prioritaskan di atas dokumentasi.

PROMPT;

    private BedrockRuntimeClient $bedrock;

    private string $modelId;

    private int $maxTokens;

    private float $temperature;

    public function __construct()
    {
        $cfg = config('services.bedrock');

        $credentials = [
            'key' => $cfg['key'],
            'secret' => $cfg['secret'],
        ];

        $this->modelId = $cfg['model_id'];
        $this->maxTokens = $cfg['max_tokens'];
        $this->temperature = $cfg['temperature'];

        $this->bedrock = new BedrockRuntimeClient([
            'version' => 'latest',
            'region' => $cfg['bedrock_region'],
            'credentials' => $credentials,
            'http' => [
                'verify' => false,
            ],
        ]);
    }

    /**
     * Ask a question enriched with database context and optional S3 chunks.
     *
     * @param  array{model: string, count: int, data: list<array<string, mixed>>}  $dbContext  From DatabaseQueryService::query()
     * @param  list<array{content: string, source: string, chunk_index: int, score: float}>  $s3Chunks  S3 RAG chunks for hybrid intent
     * @return array{answer: string, sources: list<array<string, mixed>>, model_id: string, session_id: string}
     *
     * @throws \Exception
     */
    public function ask(string $query, array $dbContext = [], array $s3Chunks = []): array
    {
        [$prompt, $sources] = $this->buildPrompt($query, $dbContext, $s3Chunks);

        $rawAnswer = $this->invokeModel($prompt);
        $answer = MarkdownToText::convert($rawAnswer);

        Log::info('TelegramRagService: answered query.', [
            'intent' => empty($s3Chunks) ? 'db' : 'hybrid',
            'db_records' => $dbContext['count'] ?? 0,
            's3_chunks' => count($s3Chunks),
        ]);

        return [
            'answer' => $answer,
            'sources' => $sources,
            'model_id' => $this->modelId,
            'session_id' => (string) Str::uuid(),
        ];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Build the full prompt and collect sources for the response payload.
     *
     * DB context always comes first (it is authoritative real-time data).
     * S3 documentation chunks follow as supplementary context (hybrid only).
     *
     * @param  array{model: string, count: int, data: list<array<string, mixed>>}  $dbContext
     * @param  list<array{content: string, source: string, chunk_index: int, score: float}>  $s3Chunks
     * @return array{0: string, 1: list<array<string, mixed>>}
     */
    private function buildPrompt(string $query, array $dbContext, array $s3Chunks): array
    {
        $sources = [];
        $sections = [];

        // --- Database context block ---
        if (! empty($dbContext['data'])) {
            $sections[] = $this->formatDbContextBlock($dbContext);
            $sources[] = [
                'source' => 'database:'.$dbContext['model'],
                'count' => $dbContext['count'],
            ];
        }

        // --- S3 documentation block (hybrid only) ---
        if (! empty($s3Chunks)) {
            $docParts = [];
            foreach ($s3Chunks as $i => $chunk) {
                $n = $i + 1;
                $docParts[] = "[Dokumentasi {$n} — {$chunk['source']}]\n{$chunk['content']}";
                $sources[] = [
                    'source' => $chunk['source'],
                    'chunk_index' => $chunk['chunk_index'],
                    'score' => $chunk['score'],
                ];
            }
            $sections[] = "=== DOKUMENTASI SISTEM ===\n".implode("\n\n---\n\n", $docParts)."\n=== AKHIR DOKUMENTASI ===";
        }

        if (empty($sections)) {
            // Fallback: no context at all — let Bedrock reply with the "no data" phrase.
            return [$query, []];
        }

        $contextBlock = implode("\n\n", $sections);

        $prompt = <<<TEXT
        Gunakan konteks berikut untuk menjawab pertanyaan klien.

        {$contextBlock}

        Pertanyaan: {$query}
        TEXT;

        return [$prompt, $sources];
    }

    /**
     * Serialize the DatabaseQueryService result into a readable plain-text block.
     *
     * Each record is presented as a numbered item with field: value pairs on
     * separate lines, making it easy for the model to enumerate results.
     *
     * @param  array{model: string, count: int, data: list<array<string, mixed>>}  $dbContext
     */
    private function formatDbContextBlock(array $dbContext): string
    {
        $modelLabel = match ($dbContext['model']) {
            'orders' => 'PESANAN ANDA',
            'materials' => 'DAFTAR MATERIAL',
            'inventory_items' => 'INVENTORI GUDANG',
            'output_entries' => 'CATATAN OUTPUT PRODUKSI',
            'rate_cards' => 'TARIF JAHIT / POTONG',
            default => strtoupper($dbContext['model']),
        };

        $header = "=== DATA {$modelLabel} ({$dbContext['count']} item) ===";

        $rows = [];
        foreach ($dbContext['data'] as $index => $record) {
            $rowNumber = $index + 1;
            $lines = ["[{$rowNumber}]"];
            foreach ($record as $field => $value) {
                $label = ucwords(str_replace('_', ' ', $field));
                $displayValue = $value === null ? 'N/A' : (is_array($value) ? json_encode($value) : (string) $value);
                $lines[] = "  {$label}: {$displayValue}";
            }
            $rows[] = implode("\n", $lines);
        }

        $body = empty($rows)
            ? '  (Tidak ada data)'
            : implode("\n\n", $rows);

        return "{$header}\n{$body}\n=== AKHIR DATA ===";
    }

    /**
     * Call the Bedrock Converse API and return the raw text response.
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
