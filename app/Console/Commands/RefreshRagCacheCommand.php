<?php

namespace App\Console\Commands;

use App\Services\Ai\S3DocumentLoader;
use Illuminate\Console\Command;

class RefreshRagCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rag:refresh-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh RAG document cache from S3';

    /**
     * Execute the console command.
     */
    public function handle(S3DocumentLoader $loader): int
    {
        $this->info('Refreshing RAG document cache...');

        $chunks = $loader->reload();

        $totalChunks = array_sum(array_map('count', $chunks));

        $this->info('Loaded '.count($chunks).' documents, '.$totalChunks.' total chunks.');

        foreach (array_keys($chunks) as $key) {
            $this->line("  • $key (".count($chunks[$key]).' chunks)');
        }

        $this->info('Cache refreshed successfully!');

        return 0;
    }
}
