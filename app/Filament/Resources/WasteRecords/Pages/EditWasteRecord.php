<?php

namespace App\Filament\Resources\WasteRecords\Pages;

use App\Filament\Resources\WasteRecords\WasteRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWasteRecord extends EditRecord
{
    protected static string $resource = WasteRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
