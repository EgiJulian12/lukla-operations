<?php

namespace App\Filament\Resources\MaterialDispatches\Pages;

use App\Filament\Resources\MaterialDispatches\MaterialDispatchResource;
use Filament\Resources\Pages\ListRecords;

class ListMaterialDispatches extends ListRecords
{
    protected static string $resource = MaterialDispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
