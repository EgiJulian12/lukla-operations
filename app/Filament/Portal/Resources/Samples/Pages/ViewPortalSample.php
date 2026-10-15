<?php

namespace App\Filament\Portal\Resources\Samples\Pages;

use App\Filament\Portal\Resources\Samples\PortalSampleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPortalSample extends ViewRecord
{
    protected static string $resource = PortalSampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali ke Daftar Sample')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => PortalSampleResource::getUrl(panel: 'portal', tenant: auth()->user()?->client)),
        ];
    }
}
