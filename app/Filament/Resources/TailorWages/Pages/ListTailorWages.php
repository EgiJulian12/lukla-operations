<?php

namespace App\Filament\Resources\TailorWages\Pages;

use App\Filament\Resources\TailorWages\TailorWageResource;
use Filament\Resources\Pages\ListRecords;

class ListTailorWages extends ListRecords
{
    protected static string $resource = TailorWageResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
