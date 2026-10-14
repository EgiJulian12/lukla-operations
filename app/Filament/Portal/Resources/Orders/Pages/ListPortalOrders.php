<?php

namespace App\Filament\Portal\Resources\Orders\Pages;

use App\Filament\Portal\Resources\Orders\PortalOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPortalOrders extends ListRecords
{
    protected static string $resource = PortalOrderResource::class;

    /**
     * Tombol pengajuan tidak muncul sendiri di Filament 5 — aksi header harus
     * dideklarasikan per halaman, dan CreateAction tidak memeriksa kebijakan
     * create sehingga visibilitasnya ditulis eksplisit.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Ajukan Pesanan')
                ->visible(fn (): bool => static::getResource()::canCreate()),
        ];
    }
}
