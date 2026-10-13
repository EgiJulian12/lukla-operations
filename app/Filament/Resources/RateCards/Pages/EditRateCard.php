<?php

namespace App\Filament\Resources\RateCards\Pages;

use App\Filament\Resources\RateCards\RateCardResource;
use Filament\Resources\Pages\EditRecord;

class EditRateCard extends EditRecord
{
    protected static string $resource = RateCardResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
