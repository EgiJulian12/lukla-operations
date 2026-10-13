<?php

namespace Tests\Unit;

use App\Services\Ai\DocumentChunker;
use App\Services\Ai\RagService;
use App\Services\Ai\S3DocumentLoader;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Result;
use Aws\S3\S3Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class RagServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.bedrock', [
            'key' => 'test-key',
            'secret' => 'test-secret',
            's3_region' => 'ap-southeast-1',
            'bedrock_region' => 'us-east-1',
            'bucket' => 'test-bucket',
            'model_id' => 'amazon.nova-lite-v1:0',
            'max_tokens' => 100,
            'temperature' => 0.1,
            'top_k' => 3,
            'chunk_size' => 600,
            'chunk_overlap' => 100,
            'cache_ttl' => 60,
        ]);
    }

    // ------------------------------------------------------------------
    // DocumentChunker unit tests (pure PHP, no AWS)
    // ------------------------------------------------------------------

    public function test_chunker_splits_text_into_chunks(): void
    {
        $chunker = new DocumentChunker(chunkSize: 100, overlap: 20);
        $text = "First paragraph with some content here.\n\nSecond paragraph is also here.\n\nThird paragraph.";

        $chunks = $chunker->chunk($text);

        $this->assertNotEmpty($chunks);
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(120, strlen($chunk)); // slight tolerance
        }
    }

    public function test_chunker_returns_single_chunk_for_short_text(): void
    {
        $chunker = new DocumentChunker(chunkSize: 600, overlap: 100);
        $text = 'Short text that fits in one chunk.';

        $chunks = $chunker->chunk($text);

        $this->assertCount(1, $chunks);
        $this->assertSame($text, $chunks[0]);
    }

    public function test_chunker_detects_supported_extensions(): void
    {
        $this->assertTrue(DocumentChunker::isSupportedExtension('docs/guide.md'));
        $this->assertTrue(DocumentChunker::isSupportedExtension('README.txt'));
        $this->assertTrue(DocumentChunker::isSupportedExtension('data.csv'));
        $this->assertFalse(DocumentChunker::isSupportedExtension('image.png'));
        $this->assertFalse(DocumentChunker::isSupportedExtension('archive.zip'));
    }

    // ------------------------------------------------------------------
    // RagService with mocked S3DocumentLoader + AWS clients
    // ------------------------------------------------------------------

    private function makeService(array $chunks = [], ?string $bedrockAnswer = null): RagService
    {
        // Mock S3DocumentLoader to return provided chunks without hitting S3.
        $loader = Mockery::mock(S3DocumentLoader::class);
        $loader->shouldReceive('getChunks')->andReturn($chunks);
        $loader->shouldReceive('reload')->andReturn($chunks);

        $service = new RagService($loader);

        // If a Bedrock answer is supplied, swap in a mock BedrockRuntimeClient.
        if ($bedrockAnswer !== null) {
            $converseResult = new Result([
                'output' => [
                    'message' => [
                        'content' => [['text' => $bedrockAnswer]],
                    ],
                ],
            ]);

            $bedrockMock = Mockery::mock(BedrockRuntimeClient::class);
            $bedrockMock->shouldReceive('converse')->andReturn($converseResult);

            // Inject via reflection (private property).
            $ref = new \ReflectionProperty(RagService::class, 'bedrock');
            $ref->setAccessible(true);
            $ref->setValue($service, $bedrockMock);
        }

        return $service;
    }

    public function test_ask_question_returns_answer_from_bedrock(): void
    {
        $service = $this->makeService(
            chunks: [],
            bedrockAnswer: 'Manufacturing involves transforming raw materials.',
        );

        $result = $service->askQuestion('What is manufacturing?');

        $this->assertSame('Manufacturing involves transforming raw materials.', $result['answer']);
        $this->assertArrayHasKey('model_id', $result);
        $this->assertArrayHasKey('session_id', $result);
    }

    public function test_ask_question_injects_document_chunks_as_context(): void
    {
        $chunks = [
            'lukla-ops.md' => [
                'Lukla Operations Engine menyatukan produksi dan payroll.',
                'Sistem mengelola inventory klien secara terpisah.',
            ],
        ];

        $capturedPrompt = null;

        $converseResult = new Result([
            'output' => ['message' => ['content' => [['text' => 'Answer based on docs.']]]],
        ]);

        $bedrockMock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrockMock->shouldReceive('converse')
            ->once()
            ->withArgs(function (array $args) use (&$capturedPrompt) {
                $capturedPrompt = $args['messages'][0]['content'][0]['text'];

                return true;
            })
            ->andReturn($converseResult);

        $loader = Mockery::mock(S3DocumentLoader::class);
        $loader->shouldReceive('getChunks')->andReturn($chunks);

        $service = new RagService($loader);

        $ref = new \ReflectionProperty(RagService::class, 'bedrock');
        $ref->setAccessible(true);
        $ref->setValue($service, $bedrockMock);

        $service->askQuestion('operasi lukla');

        $this->assertNotNull($capturedPrompt);
        $this->assertStringContainsString('CONTEXT', $capturedPrompt);
        $this->assertStringContainsString('lukla-ops.md', $capturedPrompt);
    }

    public function test_ask_question_includes_client_context_in_enriched_query(): void
    {
        $capturedPrompt = null;

        $converseResult = new Result([
            'output' => ['message' => ['content' => [['text' => 'Your 3 active orders are on track.']]]],
        ]);

        $bedrockMock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrockMock->shouldReceive('converse')
            ->once()
            ->withArgs(function (array $args) use (&$capturedPrompt) {
                $capturedPrompt = $args['messages'][0]['content'][0]['text'];

                return true;
            })
            ->andReturn($converseResult);

        $loader = Mockery::mock(S3DocumentLoader::class);
        $loader->shouldReceive('getChunks')->andReturn([]);

        $service = new RagService($loader);

        $ref = new \ReflectionProperty(RagService::class, 'bedrock');
        $ref->setAccessible(true);
        $ref->setValue($service, $bedrockMock);

        $service->askQuestion('berapa pesanan saya?', 'generate_only', [
            'client_name' => 'PT Maju Jaya',
            'active_orders' => 3,
        ]);

        $this->assertStringContainsString('PT Maju Jaya', $capturedPrompt);
        $this->assertStringContainsString('3', $capturedPrompt);
    }

    public function test_ask_question_returns_sources_when_chunks_match(): void
    {
        $chunks = [
            'guide.md' => ['Inventory management menjaga stok klien.'],
        ];

        $service = $this->makeService($chunks, 'Answer about inventory.');

        $result = $service->askQuestion('inventory klien');

        $this->assertNotEmpty($result['sources']);
        $this->assertSame('guide.md', $result['sources'][0]['source']);
    }

    public function test_ask_question_returns_empty_sources_when_no_chunks_match(): void
    {
        $service = $this->makeService(
            chunks: ['guide.md' => ['Totally unrelated content about astronomy.']],
            bedrockAnswer: 'General answer.',
        );

        $result = $service->askQuestion('xyz123nomatch');

        $this->assertEmpty($result['sources']);
    }

    public function test_get_health_status_returns_healthy_on_success(): void
    {
        $converseResult = new Result([
            'output' => ['message' => ['content' => [['text' => 'ok']]]],
        ]);

        $bedrockMock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrockMock->shouldReceive('converse')->andReturn($converseResult);

        $loader = Mockery::mock(S3DocumentLoader::class);
        $service = new RagService($loader);

        $ref = new \ReflectionProperty(RagService::class, 'bedrock');
        $ref->setAccessible(true);
        $ref->setValue($service, $bedrockMock);

        $result = $service->getHealthStatus();

        $this->assertSame('healthy', $result['status']);
    }

    public function test_get_health_status_returns_degraded_on_failure(): void
    {
        $bedrockMock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrockMock->shouldReceive('converse')->andThrow(new \RuntimeException('Connection refused'));

        $loader = Mockery::mock(S3DocumentLoader::class);
        $service = new RagService($loader);

        $ref = new \ReflectionProperty(RagService::class, 'bedrock');
        $ref->setAccessible(true);
        $ref->setValue($service, $bedrockMock);

        $result = $service->getHealthStatus();

        $this->assertSame('degraded', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_is_available_returns_true_when_bedrock_responds(): void
    {
        $converseResult = new Result([
            'output' => ['message' => ['content' => [['text' => 'ok']]]],
        ]);

        $bedrockMock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrockMock->shouldReceive('converse')->andReturn($converseResult);

        $loader = Mockery::mock(S3DocumentLoader::class);
        $service = new RagService($loader);

        $ref = new \ReflectionProperty(RagService::class, 'bedrock');
        $ref->setAccessible(true);
        $ref->setValue($service, $bedrockMock);

        $this->assertTrue($service->isAvailable());
    }

    public function test_is_available_returns_false_when_bedrock_fails(): void
    {
        $bedrockMock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrockMock->shouldReceive('converse')->andThrow(new \RuntimeException('Timeout'));

        $loader = Mockery::mock(S3DocumentLoader::class);
        $service = new RagService($loader);

        $ref = new \ReflectionProperty(RagService::class, 'bedrock');
        $ref->setAccessible(true);
        $ref->setValue($service, $bedrockMock);

        $this->assertFalse($service->isAvailable());
    }

    public function test_get_available_models_returns_empty_array(): void
    {
        $loader = Mockery::mock(S3DocumentLoader::class);
        $service = new RagService($loader);

        $this->assertSame([], $service->getAvailableModels());
    }

    public function test_upload_document_puts_object_to_s3_and_reloads_cache(): void
    {
        $s3Mock = Mockery::mock(S3Client::class);
        $s3Mock->shouldReceive('putObject')
            ->once()
            ->withArgs(function (array $args) {
                return $args['Bucket'] === 'test-bucket'
                    && str_starts_with($args['Key'], 'documents/');
            });

        $loader = Mockery::mock(S3DocumentLoader::class);
        $loader->shouldReceive('reload')->once()->andReturn([]);

        $service = new RagService($loader);

        $refS3 = new \ReflectionProperty(RagService::class, 's3');
        $refS3->setAccessible(true);
        $refS3->setValue($service, $s3Mock);

        $file = UploadedFile::fake()->create('specs.pdf', 50, 'application/pdf');
        $result = $service->uploadDocument($file);

        $this->assertTrue($result['success']);
        $this->assertSame('specs.pdf', $result['file_name']);
        $this->assertStringContainsString('specs.pdf', $result['s3_key']);
    }
}
