<?php

namespace App\Services\Ai;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service untuk komunikasi dengan FastAPI RAG endpoint.
 * Menangani query AI, upload dokumen, dan status health check.
 */
class RagService
{
    private string $baseUrl;

    private int $timeout;

    private bool $enabled;

    public function __construct()
    {
        $this->baseUrl = config('services.rag_api.url');
        $this->timeout = config('services.rag_api.timeout');
        $this->enabled = config('services.rag_api.enabled');
    }

    /**
     * Mengirim pertanyaan ke RAG endpoint dan mendapatkan respons.
     *
     * @param  string  $query  Pertanyaan yang akan dikirim
     * @param  string  $mode  Mode operasi ('generate_only' atau mode lainnya)
     * @param  array  $context  Konteks tambahan (untuk portal: client info, dll)
     * @return array Response dari RAG API
     *
     * @throws Exception
     */
    public function askQuestion(string $query, string $mode = 'generate_only', array $context = []): array
    {
        if (! $this->enabled) {
            throw new Exception('RAG API is disabled');
        }

        try {
            $payload = [
                'query' => $query,
                'mode' => $mode,
            ];

            // Tambahkan konteks jika ada (untuk portal chat)
            if (! empty($context)) {
                $payload['context'] = $context;
            }

            $response = $this->makeRequest()
                ->post('/ask', $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('RAG API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query' => $query,
            ]);

            throw new Exception('RAG API returned error: '.$response->status());
        } catch (ConnectionException $e) {
            Log::error('RAG API connection failed', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            throw new Exception('Unable to connect to RAG API service');
        } catch (RequestException $e) {
            Log::error('RAG API request exception', [
                'message' => $e->getMessage(),
                'query' => $query,
            ]);

            throw new Exception('RAG API returned error: '.$e->response?->status());
        }
    }

    /**
     * Mengecek status health RAG API.
     *
     * @return array Status health dari API
     *
     * @throws Exception
     */
    public function getHealthStatus(): array
    {
        if (! $this->enabled) {
            return ['status' => 'disabled', 'message' => 'RAG API is disabled'];
        }

        try {
            $response = $this->makeRequest()
                ->get('/health');

            if ($response->successful()) {
                return $response->json();
            }

            return [
                'status' => 'error',
                'message' => 'Health check failed with status: '.$response->status(),
            ];
        } catch (ConnectionException $e) {
            Log::error('RAG API health check failed', [
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Unable to connect to RAG API service',
            ];
        }
    }

    /**
     * Upload dokumen ke RAG service untuk indexing.
     *
     * @param  UploadedFile  $file  File yang akan diupload
     * @return array Response dari upload API
     *
     * @throws Exception
     */
    public function uploadDocument(UploadedFile $file): array
    {
        if (! $this->enabled) {
            throw new Exception('RAG API is disabled');
        }

        try {
            $response = $this->makeRequest()
                ->attach('file', $file->getContent(), $file->getClientOriginalName())
                ->post('/upload');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('RAG API upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'filename' => $file->getClientOriginalName(),
            ]);

            throw new Exception('Document upload failed: '.$response->status());
        } catch (ConnectionException $e) {
            Log::error('RAG API upload connection failed', [
                'message' => $e->getMessage(),
                'filename' => $file->getClientOriginalName(),
            ]);

            throw new Exception('Unable to connect to RAG API service for upload');
        }
    }

    /**
     * Mendapatkan list model yang tersedia.
     *
     * @return array List model dari API
     *
     * @throws Exception
     */
    public function getAvailableModels(): array
    {
        if (! $this->enabled) {
            return [];
        }

        try {
            $response = $this->makeRequest()
                ->get('/models');

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('RAG API models request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        } catch (ConnectionException $e) {
            Log::error('RAG API models connection failed', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Helper untuk membuat HTTP request dengan konfigurasi yang konsisten.
     */
    private function makeRequest(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }

    /**
     * Check apakah RAG service aktif dan dapat diakses.
     */
    public function isAvailable(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        try {
            $health = $this->getHealthStatus();

            return $health['status'] === 'healthy' || $health['status'] === 'ok';
        } catch (Exception $e) {
            return false;
        }
    }
}
