<?php

namespace App\Console\Commands;

use App\Services\Ai\RagService;
use Exception;
use Illuminate\Console\Command;

class TestRagServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:rag-service {--query=What is manufacturing?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test RAG service connectivity and functionality';

    /**
     * Execute the console command.
     */
    public function handle(RagService $ragService): int
    {
        $this->info('Testing RAG Service Connection...');

        // Test health check
        $this->line('1. Checking health status...');
        try {
            $health = $ragService->getHealthStatus();
            $this->info('Health Status: '.json_encode($health, JSON_PRETTY_PRINT));
        } catch (Exception $e) {
            $this->error('Health check failed: '.$e->getMessage());
        }

        // Test availability
        $this->line('2. Checking availability...');
        $available = $ragService->isAvailable();
        $this->info('Service Available: '.($available ? 'Yes' : 'No'));

        // Test models
        $this->line('3. Getting available models...');
        try {
            $models = $ragService->getAvailableModels();
            $this->info('Available Models: '.json_encode($models, JSON_PRETTY_PRINT));
        } catch (Exception $e) {
            $this->error('Failed to get models: '.$e->getMessage());
        }

        // Test question
        $query = $this->option('query');
        $this->line("4. Testing question: \"{$query}\"");
        try {
            $response = $ragService->askQuestion($query);
            $this->info('Response: '.json_encode($response, JSON_PRETTY_PRINT));
        } catch (Exception $e) {
            $this->error('Question failed: '.$e->getMessage());
        }

        // Test with context
        $this->line('5. Testing question with context...');
        $context = [
            'client_id' => 123,
            'client_name' => 'Test Manufacturing Co',
            'active_orders' => 3,
        ];

        try {
            $response = $ragService->askQuestion(
                'What are my current orders?',
                'generate_only',
                $context
            );
            $this->info('Context Response: '.json_encode($response, JSON_PRETTY_PRINT));
        } catch (Exception $e) {
            $this->error('Context question failed: '.$e->getMessage());
        }

        $this->info('RAG Service test completed!');

        return 0;
    }
}
