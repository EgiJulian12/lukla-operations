<?php

namespace App\Filament\Resources\QcBonuses\Pages;

use App\Filament\Resources\QcBonuses\QcBonusResource;
use Filament\Resources\Pages\ListRecords;

class ListQcBonuses extends ListRecords
{
    protected static string $resource = QcBonusResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
