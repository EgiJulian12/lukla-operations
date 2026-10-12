<?php

namespace App\Filament\Resources\ProductionLines\Pages;

use App\Filament\Resources\ProductionLines\ProductionLineResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionLine extends EditRecord
{
    protected static string $resource = ProductionLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Hapus'),
        ];
    }
}
