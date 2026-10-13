<?php

namespace App\Services\Ai;

/**
 * Splits raw document text into overlapping chunks suitable for RAG retrieval.
 *
 * Strategy (mirrors the Python implementation):
 *  1. Split on paragraph breaks (\n\n) first — preserves semantic units.
 *  2. Accumulate paragraphs into a chunk until chunk_size is reached.
 *  3. When a single paragraph exceeds chunk_size, split it by sentence
 *     boundaries instead.
 *  4. Each new chunk carries an overlap tail from the previous one so
 *     context is not severed at boundaries.
 */
class DocumentChunker
{
    public function __construct(
        private readonly int $chunkSize = 600,
        private readonly int $overlap = 100,
    ) {}

    /**
     * @return list<string>
     */
    public function chunk(string $text): array
    {
        // Normalise line endings and collapse runs of blank lines.
        $text = str_replace("\r\n", "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        $paragraphs = array_filter(
            preg_split('/\n\n+/', $text),
            fn (string $p) => trim($p) !== '',
        );
        $paragraphs = array_map('trim', array_values($paragraphs));

        $chunks = [];
        $currentChunk = '';

        foreach ($paragraphs as $para) {
            if (strlen($currentChunk) + strlen($para) + 1 <= $this->chunkSize) {
                $currentChunk = $currentChunk === ''
                    ? $para
                    : $currentChunk."\n\n".$para;
            } else {
                if ($currentChunk !== '') {
                    $chunks[] = $currentChunk;
                }

                if (strlen($para) > $this->chunkSize) {
                    // Paragraph is too big on its own — split by sentences.
                    $sentences = $this->splitSentences($para);
                    $buf = '';

                    foreach ($sentences as $sentence) {
                        if (strlen($buf) + strlen($sentence) + 1 <= $this->chunkSize) {
                            $buf = $buf === '' ? $sentence : $buf.' '.$sentence;
                        } else {
                            if ($buf !== '') {
                                $chunks[] = $buf;
                            }
                            $buf = $sentence;
                        }
                    }

                    $currentChunk = $buf;
                } else {
                    // Carry an overlap tail from the previous chunk.
                    $tail = $currentChunk !== ''
                        ? substr($currentChunk, -$this->overlap)
                        : '';

                    $currentChunk = $tail !== ''
                        ? $tail."\n\n".$para
                        : $para;
                }
            }
        }

        if ($currentChunk !== '') {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    /**
     * Split text on sentence-ending punctuation followed by whitespace.
     *
     * @return list<string>
     */
    private function splitSentences(string $text): array
    {
        // Split after . ! ? when followed by one or more spaces.
        $parts = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(
            array_map('trim', $parts ?: []),
            fn (string $s) => $s !== '',
        ));
    }

    /**
     * Returns true when the S3 object key points to a text document we
     * want to index (same extension list as the Python service).
     */
    public static function isSupportedExtension(string $key): bool
    {
        $supported = ['md', 'txt', 'html', 'htm', 'json', 'csv'];
        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));

        return in_array($ext, $supported, strict: true);
    }
}
