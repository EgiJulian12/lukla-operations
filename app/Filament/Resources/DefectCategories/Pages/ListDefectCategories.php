<?php

namespace App\Filament\Resources\DefectCategories\Pages;

use App\Filament\Resources\DefectCategories\DefectCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDefectCategories extends ListRecords
{
    protected static string $resource = DefectCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Kategori'),
        ];
    }
}
