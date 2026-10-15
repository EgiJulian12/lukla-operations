<?php

namespace App\Services\Ai;

/**
 * Keyword-based intent router for Telegram bot queries.
 *
 * Classifies a query string into one of three intents:
 *  - "db"     — the answer requires live database data
 *  - "doc"    — the answer should come from S3 documentation (existing RAG)
 *  - "hybrid" — the answer benefits from both live data and documentation
 *
 * Also extracts rough entity filters (e.g. order number, status) from the query.
 */
class IntentRouter
{
    /**
     * Keyword map: model key → list of trigger words (lowercase).
     *
     * Order matters — more specific entries are placed first to prevent
     * substring collisions (e.g. "tarif jahit" should match rate_cards,
     * not output_entries via the "jahit" keyword).
     *
     * @var array<string, list<string>>
     */
    private const MODEL_KEYWORDS = [
        'rate_cards' => [
            'rate card', 'tarif jahit', 'ongkos jahit', 'ongkos potong',
            'rate', 'tarif', 'ongkos', 'borongan',
        ],
        'orders' => [
            'order', 'pesanan', 'orderan',
        ],
        'materials' => [
            'material', 'bahan baku', 'bahan', 'fabric', 'kain', 'aksesoris',
        ],
        'inventory_items' => [
            'inventory', 'stok', 'gudang', 'stock', 'persediaan',
        ],
        'output_entries' => [
            'output produksi', 'output', 'penjahit', 'hasil jahit', 'hasil produksi',
        ],
    ];

    /**
     * Whole-word / phrase doc-intent triggers.
     *
     * Stored as patterns to avoid false substring matches (e.g. "how" inside
     * "show"). Each entry is matched with a word-boundary regex so "show" does
     * not trigger the "how" pattern.
     *
     * @var list<string>
     */
    private const DOC_KEYWORDS = [
        'how', 'why', 'what is', 'bagaimana', 'kenapa', 'mengapa',
        'apa itu', 'jelaskan', 'explain', 'proses', 'alur', 'prosedur',
        'langkah', 'cara', 'definisi', 'maksud',
    ];

    /**
     * Detect the intent of a query string.
     *
     * @return array{intent: string, model: string|null, filters: array<string, mixed>}
     */
    public function detectIntent(string $query): array
    {
        $lowerQuery = mb_strtolower($query);

        $matchedModel = $this->matchModel($lowerQuery);
        $hasDocKeyword = $this->hasDocKeyword($lowerQuery);
        $filters = $this->extractFilters($lowerQuery, $query);

        if ($matchedModel === null) {
            return [
                'intent' => 'doc',
                'model' => null,
                'filters' => $filters,
            ];
        }

        $intent = $hasDocKeyword ? 'hybrid' : 'db';

        return [
            'intent' => $intent,
            'model' => $matchedModel,
            'filters' => $filters,
        ];
    }

    /**
     * Scan the query for the first matching model keyword and return the model key.
     */
    private function matchModel(string $lowerQuery): ?string
    {
        foreach (self::MODEL_KEYWORDS as $model => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lowerQuery, $keyword)) {
                    return $model;
                }
            }
        }

        return null;
    }

    /**
     * Check whether the query contains any documentation-intent keyword.
     *
     * Uses a word-boundary regex to avoid false positives from substring
     * matches (e.g. "show" should not match the "how" pattern).
     */
    private function hasDocKeyword(string $lowerQuery): bool
    {
        foreach (self::DOC_KEYWORDS as $keyword) {
            // Multi-word phrases: use a simple str_contains after normalisation.
            // Single words: wrap in \b word boundaries to prevent partial hits.
            $isPhrase = str_contains($keyword, ' ');

            if ($isPhrase) {
                if (str_contains($lowerQuery, $keyword)) {
                    return true;
                }
            } else {
                if (preg_match('/\b'.preg_quote($keyword, '/').'\\b/u', $lowerQuery)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Extract structured entity filters from the raw query.
     *
     * Currently extracts:
     *  - order_number: matches patterns like ORD-0001, ORD-2024-001
     *  - status: matches "status:value" inline tokens
     *
     * @return array<string, mixed>
     */
    private function extractFilters(string $lowerQuery, string $originalQuery): array
    {
        $filters = [];

        // Match an order number pattern: ORD-<alphanumeric-with-dashes>
        if (preg_match('/\b(ORD-[\w\-]+)/i', $originalQuery, $matches)) {
            $filters['order_number'] = $matches[1];
        }

        // Match inline status filter: "status:approved", "status:pending", etc.
        if (preg_match('/status:(\w+)/i', $lowerQuery, $statusMatch)) {
            $filters['status'] = $statusMatch[1];
        }

        // Match inventory state filter: "state:raw", "state:wip", etc.
        if (preg_match('/state:(\w+)/i', $lowerQuery, $stateMatch)) {
            $filters['state'] = $stateMatch[1];
        }

        return $filters;
    }
}
