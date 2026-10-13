<?php

namespace App\Services\Ai;

use Aws\S3\S3Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Downloads text documents from S3, chunks them, and stores the result
 * in Laravel's cache so they survive between requests without hitting S3
 * on every call.
 *
 * Cache structure stored under key "rag_document_chunks":
 *
 *   array<string, list<string>>   // [ s3_key => [ chunk, chunk, ... ] ]
 */
class S3DocumentLoader
{
    private const CACHE_KEY = 'rag_document_chunks';

    private S3Client $s3;

    private DocumentChunker $chunker;

    private string $bucket;

    private int $cacheTtl;

    public function __construct(DocumentChunker $chunker)
    {
        $cfg = config('services.bedrock');

        $this->chunker = $chunker;
        $this->bucket = $cfg['bucket'];
        $this->cacheTtl = $cfg['cache_ttl'];

        $this->s3 = new S3Client([
            'version' => 'latest',
            'region' => $cfg['s3_region'],
            'credentials' => [
                'key' => $cfg['key'],
                'secret' => $cfg['secret'],
            ],
        ]);
    }

    /**
     * Returns the full chunk map, loading from cache or S3 as needed.
     *
     * @return array<string, list<string>>
     */
    public function getChunks(): array
    {
        /** @var array<string, list<string>>|null $cached */
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        return $this->loadFromS3();
    }

    /**
     * Forces a reload from S3 (e.g. after a new document is uploaded)
     * and refreshes the cache.
     *
     * @return array<string, list<string>>
     */
    public function reload(): array
    {
        Cache::forget(self::CACHE_KEY);

        return $this->loadFromS3();
    }

    /**
     * @return array<string, list<string>>
     */
    private function loadFromS3(): array
    {
        $chunks = [];

        try {
            $result = $this->s3->listObjectsV2(['Bucket' => $this->bucket]);
            $objects = $result->get('Contents') ?? [];

            if (empty($objects)) {
                Log::warning('RAG S3DocumentLoader: bucket is empty, no documents loaded.', [
                    'bucket' => $this->bucket,
                ]);

                return [];
            }

            foreach ($objects as $object) {
                $key = $object['Key'];

                if (! DocumentChunker::isSupportedExtension($key)) {
                    continue;
                }

                try {
                    $body = $this->s3->getObject([
                        'Bucket' => $this->bucket,
                        'Key' => $key,
                    ]);

                    $text = (string) $body['Body'];
                    $documentChunks = $this->chunker->chunk($text);
                    $chunks[$key] = $documentChunks;

                    Log::debug('RAG S3DocumentLoader: loaded document.', [
                        'key' => $key,
                        'chunks' => count($documentChunks),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('RAG S3DocumentLoader: failed to load object.', [
                        'key' => $key,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $totalChunks = array_sum(array_map('count', $chunks));

            Log::info('RAG S3DocumentLoader: document cache built.', [
                'files' => count($chunks),
                'chunks' => $totalChunks,
            ]);

            Cache::put(self::CACHE_KEY, $chunks, $this->cacheTtl);

        } catch (\Throwable $e) {
            Log::error('RAG S3DocumentLoader: S3 listing failed.', [
                'error' => $e->getMessage(),
            ]);
        }

        return $chunks;
    }
}
