<?php

namespace App\Filament\Resources\OutputEntries\Pages;

use App\Filament\Resources\OutputEntries\OutputEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOutputEntries extends ListRecords
{
    protected static string $resource = OutputEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Catat Output'),
        ];
    }
}
