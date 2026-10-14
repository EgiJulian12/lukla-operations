<?php

namespace App\Filament\Resources\Samples\Pages;

use App\Filament\Resources\Samples\SampleResource;
use App\Models\Article;
use App\Services\Sample\SampleService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Membuat sample lewat SampleService (FR-001).
 *
 * Klien diambil dari artikel di dalam service, bukan dari formulir, sehingga
 * sample tidak dapat diatribusikan ke klien yang salah.
 */
class CreateSample extends CreateRecord
{
    protected static string $resource = SampleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SampleService::class)->createSample(
            article: Article::findOrFail($data['article_id']),
            code: $data['code'],
            title: $data['title'],
            notes: $data['notes'] ?? null,
        );
    }
}
