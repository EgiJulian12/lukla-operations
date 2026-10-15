<?php

namespace App\Filament\Resources\Samples\Pages;

use App\Filament\Resources\ApprovedSamples\Actions\ConvertToOrderAction;
use App\Filament\Resources\Samples\SampleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSample extends ViewRecord
{
    protected static string $resource = SampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Edit Data Sample')
                ->visible(fn (): bool => (bool) auth()->user()?->can('update', $this->getRecord())),
            ConvertToOrderAction::make()
                ->record($this->getRecord())
                ->label('Konfirmasi & Lanjut Produksi Massal')
                ->visible(fn (): bool => $this->getRecord()->isReadyForMassProduction() && (bool) auth()->user()?->can('convertToOrder', $this->getRecord())),
        ];
    }
}
