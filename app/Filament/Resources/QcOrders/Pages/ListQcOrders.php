<?php

namespace App\Filament\Resources\QcOrders\Pages;

use App\Filament\Resources\QcOrders\QcOrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListQcOrders extends ListRecords
{
    protected static string $resource = QcOrderResource::class;

    public function getTabs(): array
    {
        return [
            'ready_for_qc' => Tab::make('Ada Barang Siap QC')
                ->badge(fn (): int => (clone $this->getFilteredTableQuery())
                    ->whereHas('assemblies', fn ($q) => $q->pendingQc())
                    ->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereHas('assemblies', fn ($q) => $q->pendingQc())),
            'all' => Tab::make('Semua Pesanan Aktif'),
            'completed' => Tab::make('Selesai QC')
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereRaw('quantity <= (SELECT COALESCE(SUM(quantity), 0) FROM qc_records WHERE qc_records.order_id = orders.id AND qc_records.result = \'pass\')')),
        ];
    }
}
