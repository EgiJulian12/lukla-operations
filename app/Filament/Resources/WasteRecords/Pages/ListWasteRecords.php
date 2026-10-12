<?php

namespace App\Filament\Resources\WasteRecords\Pages;

use App\Filament\Resources\WasteRecords\WasteRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWasteRecords extends ListRecords
{
    protected static string $resource = WasteRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat Sisa Potongan'),
        ];
    }
}
