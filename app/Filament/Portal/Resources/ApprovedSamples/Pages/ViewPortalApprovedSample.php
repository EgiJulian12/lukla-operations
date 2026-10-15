<?php

namespace App\Filament\Portal\Resources\ApprovedSamples\Pages;

use App\Filament\Portal\Resources\ApprovedSamples\PortalApprovedSampleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPortalApprovedSample extends ViewRecord
{
    protected static string $resource = PortalApprovedSampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali ke Daftar Sample Disetujui')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => PortalApprovedSampleResource::getUrl(panel: 'portal', tenant: auth()->user()?->client)),
        ];
    }
}
