<?php

namespace App\Filament\Portal\Resources\Samples\Pages;

use App\Filament\Portal\Resources\Samples\PortalSampleResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalSamples extends ListRecords
{
    protected static string $resource = PortalSampleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
