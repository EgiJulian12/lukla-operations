<?php

namespace App\Filament\Resources\QcRecords\Pages;

use App\Filament\Resources\QcRecords\QcRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListQcRecords extends ListRecords
{
    protected static string $resource = QcRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Periksa Produk'),
        ];
    }
}
