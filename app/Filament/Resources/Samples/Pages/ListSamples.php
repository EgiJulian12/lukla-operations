<?php

namespace App\Filament\Resources\Samples\Pages;

use App\Filament\Resources\Samples\SampleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSamples extends ListRecords
{
    protected static string $resource = SampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Sample'),
        ];
    }
}
