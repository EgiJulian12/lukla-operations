<?php

namespace Tests\Unit;

use App\Services\Ai\RagService;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RagServiceTest extends TestCase
{
    private RagService $ragService;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up configuration for testing
        Config::set('services.rag_api.url', 'http://localhost:8080');
        Config::set('services.rag_api.timeout', 30);
        Config::set('services.rag_api.enabled', true);

        $this->ragService = new RagService;
    }

    public function test_ask_question_returns_successful_response(): void
    {
        // Arrange
        $expectedResponse = [
            'answer' => 'This is a test answer about manufacturing processes.',
            'status' => 'success',
        ];

        Http::fake([
            'http://localhost:8080/*' => Http::response($expectedResponse, 200),
        ]);

        // Act
        $result = $this->ragService->askQuestion('What is manufacturing?');

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_ask_question_with_context_includes_context_in_request(): void
    {
        // Arrange
        $context = [
            'client_id' => 123,
            'client_name' => 'Test Client',
            'active_orders' => 5,
        ];

        Http::fake([
            'http://localhost:8080/*' => Http::response(['answer' => 'Contextualized answer'], 200),
        ]);

        // Act
        $result = $this->ragService->askQuestion('What are my orders?', 'generate_only', $context);

        // Assert
        $this->assertEquals(['answer' => 'Contextualized answer'], $result);
    }

    public function test_ask_question_throws_exception_when_api_disabled(): void
    {
        // Arrange
        Config::set('services.rag_api.enabled', false);
        $ragService = new RagService;

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('RAG API is disabled');

        $ragService->askQuestion('Test question');
    }

    public function test_ask_question_throws_exception_on_connection_failure(): void
    {
        // Arrange
        Http::fake(function () {
            throw new ConnectionException('Connection failed');
        });

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to connect to RAG API service');

        $this->ragService->askQuestion('Test question');
    }

    public function test_ask_question_throws_exception_on_api_error(): void
    {
        // Arrange
        Http::fake([
            'http://localhost:8080/*' => Http::response(['error' => 'Internal server error'], 500),
        ]);

        // Act & Assert
        $this->expectException(Exception::class);

        try {
            $this->ragService->askQuestion('Test question');
        } catch (Exception $e) {
            // The service should catch and convert HTTP exceptions to our custom message
            $this->assertStringContainsString('RAG API returned error', $e->getMessage());
            throw $e;
        }
    }

    public function test_get_health_status_returns_successful_response(): void
    {
        // Arrange
        $expectedResponse = ['status' => 'healthy', 'service' => 'rag-api'];

        Http::fake([
            'http://localhost:8080/*' => Http::response($expectedResponse, 200),
        ]);

        // Act
        $result = $this->ragService->getHealthStatus();

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_get_health_status_returns_disabled_when_api_disabled(): void
    {
        // Arrange
        Config::set('services.rag_api.enabled', false);
        $ragService = new RagService;

        // Act
        $result = $ragService->getHealthStatus();

        // Assert
        $this->assertEquals([
            'status' => 'disabled',
            'message' => 'RAG API is disabled',
        ], $result);
    }

    public function test_get_health_status_handles_connection_failure(): void
    {
        // Arrange
        Http::fake(function () {
            throw new ConnectionException('Connection failed');
        });

        // Act
        $result = $this->ragService->getHealthStatus();

        // Assert
        $this->assertEquals([
            'status' => 'error',
            'message' => 'Unable to connect to RAG API service',
        ], $result);
    }

    public function test_upload_document_returns_successful_response(): void
    {
        // Arrange
        $file = UploadedFile::fake()->create('test.pdf', 100);
        $expectedResponse = [
            'message' => 'Document uploaded successfully',
            'document_id' => 'doc_123',
        ];

        Http::fake([
            'http://localhost:8080/*' => Http::response($expectedResponse, 200),
        ]);

        // Act
        $result = $this->ragService->uploadDocument($file);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_upload_document_throws_exception_when_api_disabled(): void
    {
        // Arrange
        Config::set('services.rag_api.enabled', false);
        $ragService = new RagService;
        $file = UploadedFile::fake()->create('test.pdf', 100);

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('RAG API is disabled');

        $ragService->uploadDocument($file);
    }

    public function test_get_available_models_returns_models_list(): void
    {
        // Arrange
        $expectedResponse = [
            'models' => ['claude-3-sonnet', 'claude-3-haiku'],
        ];

        Http::fake([
            'http://localhost:8080/*' => Http::response($expectedResponse, 200),
        ]);

        // Act
        $result = $this->ragService->getAvailableModels();

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    public function test_get_available_models_returns_empty_array_when_disabled(): void
    {
        // Arrange
        Config::set('services.rag_api.enabled', false);
        $ragService = new RagService;

        // Act
        $result = $ragService->getAvailableModels();

        // Assert
        $this->assertEquals([], $result);
    }

    public function test_is_available_returns_true_when_service_healthy(): void
    {
        // Arrange
        Http::fake([
            'http://localhost:8080/*' => Http::response(['status' => 'healthy'], 200),
        ]);

        // Act
        $result = $this->ragService->isAvailable();

        // Assert
        $this->assertTrue($result);
    }

    public function test_is_available_returns_false_when_service_disabled(): void
    {
        // Arrange
        Config::set('services.rag_api.enabled', false);
        $ragService = new RagService;

        // Act
        $result = $ragService->isAvailable();

        // Assert
        $this->assertFalse($result);
    }

    public function test_is_available_returns_false_on_connection_failure(): void
    {
        // Arrange
        Http::fake(function () {
            throw new ConnectionException('Connection failed');
        });

        // Act
        $result = $this->ragService->isAvailable();

        // Assert
        $this->assertFalse($result);
    }
}
