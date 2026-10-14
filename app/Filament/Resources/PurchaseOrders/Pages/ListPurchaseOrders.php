<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseOrders extends ListRecords
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah PO'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Pengajuan Gudang'),
            'submitted' => Tab::make('Menunggu Persetujuan')
                ->badge(fn (): int => PurchaseOrder::query()
                    ->where('status', PurchaseOrderStatus::SUBMITTED->value)
                    ->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PurchaseOrderStatus::SUBMITTED->value)),
            'approved' => Tab::make('Disetujui')
                ->badge(fn (): int => PurchaseOrder::query()
                    ->where('status', PurchaseOrderStatus::APPROVED->value)
                    ->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PurchaseOrderStatus::APPROVED->value)),
            'draft' => Tab::make('Draf')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PurchaseOrderStatus::DRAFT->value)),
        ];
    }
}
