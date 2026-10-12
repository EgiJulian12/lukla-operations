<?php

namespace App\Filament\Portal\Resources\InventoryItems\Pages;

use App\Filament\Portal\Resources\InventoryItems\PortalInventoryItemResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalInventoryItems extends ListRecords
{
    protected static string $resource = PortalInventoryItemResource::class;
}
