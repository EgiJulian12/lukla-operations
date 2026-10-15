<?php

namespace App\Filament\Resources\Samples\Pages;

use App\Filament\Resources\ApprovedSamples\Actions\ConvertToOrderAction;
use App\Filament\Resources\Samples\SampleResource;
use Filament\Resources\Pages\EditRecord;

class EditSample extends EditRecord
{
    protected static string $resource = SampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ConvertToOrderAction::make()
                ->record($this->getRecord())
                ->label('Konfirmasi & Lanjut Produksi Massal')
                ->visible(fn (): bool => $this->getRecord()->isReadyForMassProduction()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
