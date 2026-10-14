<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Daftar order massal yang menunggu dikerjakan di lantai produksi.
 */
class ListProductionOrders extends ListRecords
{
    protected static string $resource = ProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
