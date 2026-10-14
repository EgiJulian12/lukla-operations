<?php

namespace App\Filament\Resources\MyPurchaseOrders\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\MyPurchaseOrders\MyPurchaseOrderResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMyPurchaseOrders extends ListRecords
{
    protected static string $resource = MyPurchaseOrderResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Pengajuan Gudang'),
            'submitted' => Tab::make('Menunggu Persetujuan')
                ->badge(fn (): int => static::getResource()::getEloquentQuery()
                    ->where('status', PurchaseOrderStatus::SUBMITTED->value)
                    ->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PurchaseOrderStatus::SUBMITTED->value)),
            'approved' => Tab::make('Disetujui')
                ->badge(fn (): int => static::getResource()::getEloquentQuery()
                    ->where('status', PurchaseOrderStatus::APPROVED->value)
                    ->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PurchaseOrderStatus::APPROVED->value)),
            'draft' => Tab::make('Draf')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', PurchaseOrderStatus::DRAFT->value)),
        ];
    }
}
