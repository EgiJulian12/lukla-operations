<?php

namespace App\Filament\Resources\OverheadRecords\Pages;

use App\Filament\Resources\OverheadRecords\OverheadRecordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOverheadRecord extends CreateRecord
{
    protected static string $resource = OverheadRecordResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
