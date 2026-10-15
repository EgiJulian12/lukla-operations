<?php

namespace App\Filament\Portal\Resources\ApprovedSamples\Pages;

use App\Filament\Portal\Resources\ApprovedSamples\PortalApprovedSampleResource;
use Filament\Resources\Pages\ListRecords;

class ListPortalApprovedSamples extends ListRecords
{
    protected static string $resource = PortalApprovedSampleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
