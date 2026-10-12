<?php

namespace App\Filament\Portal\Resources\Orders\Pages;

use App\Filament\Portal\Resources\Orders\PortalOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalOrders extends ListRecords
{
    protected static string $resource = PortalOrderResource::class;
}
