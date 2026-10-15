<?php

namespace App\Filament\Portal\Resources\Invoices\Pages;

use App\Filament\Portal\Resources\Invoices\PortalInvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalInvoices extends ListRecords
{
    protected static string $resource = PortalInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
