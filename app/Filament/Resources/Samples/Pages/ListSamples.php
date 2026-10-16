<?php

namespace App\Filament\Resources\Samples\Pages;

use App\Filament\Resources\Samples\SampleResource;
use App\Filament\Resources\Samples\Widgets\PendingSampleOrdersWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSamples extends ListRecords
{
    protected static string $resource = SampleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buat Sample Mandiri'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PendingSampleOrdersWidget::class,
        ];
    }
}
