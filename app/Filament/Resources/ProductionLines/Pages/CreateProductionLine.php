<?php

namespace App\Filament\Resources\ProductionLines\Pages;

use App\Filament\Resources\ProductionLines\ProductionLineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionLine extends CreateRecord
{
    protected static string $resource = ProductionLineResource::class;
}
