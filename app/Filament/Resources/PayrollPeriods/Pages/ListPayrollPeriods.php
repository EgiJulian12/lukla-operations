<?php

namespace App\Filament\Resources\PayrollPeriods\Pages;

use App\Filament\Resources\PayrollPeriods\PayrollPeriodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrollPeriods extends ListRecords
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Buka Periode'),
        ];
    }
}
