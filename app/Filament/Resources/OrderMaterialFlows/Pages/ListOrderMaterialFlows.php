<?php

namespace App\Filament\Resources\OrderMaterialFlows\Pages;

use App\Enums\OrderMaterialStatus;
use App\Filament\Resources\OrderMaterialFlows\OrderMaterialFlowResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrderMaterialFlows extends ListRecords
{
    protected static string $resource = OrderMaterialFlowResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Semua Pesanan'),
            'need_action' => Tab::make('Butuh Tindakan')
                ->badge(fn (): int => static::getResource()::getEloquentQuery()
                    ->whereIn('warehouse_material_status', [
                        OrderMaterialStatus::EMPTY->value,
                        OrderMaterialStatus::ITEM_REQUEST->value,
                        OrderMaterialStatus::CLIENT_CONFIRMATION->value,
                        OrderMaterialStatus::PO_AND_CLIENT->value,
                        OrderMaterialStatus::DELIVERY_IN_TRANSIT->value,
                    ])->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('warehouse_material_status', [
                    OrderMaterialStatus::EMPTY->value,
                    OrderMaterialStatus::ITEM_REQUEST->value,
                    OrderMaterialStatus::CLIENT_CONFIRMATION->value,
                    OrderMaterialStatus::PO_AND_CLIENT->value,
                    OrderMaterialStatus::DELIVERY_IN_TRANSIT->value,
                ])),
            'standby' => Tab::make('Standby di Gudang')
                ->badge(fn (): int => static::getResource()::getEloquentQuery()
                    ->where('warehouse_material_status', OrderMaterialStatus::STANDBY->value)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('warehouse_material_status', OrderMaterialStatus::STANDBY->value)),
            'cutting_and_transit' => Tab::make('Cutting & Kirim')
                ->badge(fn (): int => static::getResource()::getEloquentQuery()
                    ->whereIn('warehouse_material_status', [
                        OrderMaterialStatus::PREPARING_DELIVERY->value,
                        OrderMaterialStatus::DISPATCHED->value,
                    ])->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('warehouse_material_status', [
                    OrderMaterialStatus::PREPARING_DELIVERY->value,
                    OrderMaterialStatus::DISPATCHED->value,
                ])),
            'received' => Tab::make('Diterima Produksi')
                ->badge(fn (): int => static::getResource()::getEloquentQuery()
                    ->where('warehouse_material_status', OrderMaterialStatus::RECEIVED_BY_PRODUCTION->value)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('warehouse_material_status', OrderMaterialStatus::RECEIVED_BY_PRODUCTION->value)),
        ];
    }
}
