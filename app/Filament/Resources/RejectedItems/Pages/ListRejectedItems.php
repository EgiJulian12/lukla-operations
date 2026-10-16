<?php

namespace App\Filament\Resources\RejectedItems\Pages;

use App\Filament\Resources\RejectedItems\RejectedItemResource;
use Filament\Resources\Pages\ListRecords;

class ListRejectedItems extends ListRecords
{
    protected static string $resource = RejectedItemResource::class;

    public function mount(): void
    {
        $this->redirect(url('/admin/qc-records?activeTab=scrap'));
    }
}
