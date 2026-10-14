<?php

namespace App\Filament\Resources\Samples\Pages;

use App\Filament\Resources\Samples\SampleResource;
use Filament\Resources\Pages\EditRecord;

class EditSample extends EditRecord
{
    protected static string $resource = SampleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
