<?php

namespace App\Filament\Resources\DefectCategories\Pages;

use App\Filament\Resources\DefectCategories\DefectCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDefectCategory extends EditRecord
{
    protected static string $resource = DefectCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Hapus'),
        ];
    }
}
