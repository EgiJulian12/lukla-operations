<?php

namespace App\Filament\Resources\OverheadRecords\Pages;

use App\Filament\Resources\OverheadRecords\OverheadRecordResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOverheadRecord extends EditRecord
{
    protected static string $resource = OverheadRecordResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Hapus'),
        ];
    }
}
