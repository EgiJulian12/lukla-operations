<?php

namespace App\Filament\Portal\Resources\InventoryItems\Tables;

use App\Enums\InventoryState;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Saldo stok titipan klien per pesanan.
 *
 * Klien tidak melihat lokasi rak internal maupun catatan produksi pabrik,
 * hanya saldo bahan yang memang miliknya.
 */
class PortalInventoryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('material.name')
                    ->label('Bahan')
                    ->searchable(),
                TextColumn::make('order.order_number')
                    ->label('Pesanan')
                    ->searchable(),
                TextColumn::make('batch')
                    ->label('Batch')
                    ->placeholder('-'),
                TextColumn::make('state')
                    ->label('Keadaan')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Saldo')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uom')
                    ->label('Satuan'),
            ])
            ->filters([
                SelectFilter::make('state')
                    ->label('Keadaan')
                    ->options(InventoryState::class),
            ])
            ->poll('30s')
            ->defaultSort('created_at', 'desc');
    }
}
