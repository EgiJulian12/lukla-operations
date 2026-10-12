<?php

namespace App\Filament\Resources\InventoryItems\Tables;

use App\Enums\InventoryState;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Grid inventaris multi-tenant — SRS view #3.
 *
 * Tidak ada aksi hapus: stok tidak pernah dihapus, dan InventoryItemPolicy
 * menolaknya. Koreksi jumlah dilakukan lewat pergerakan penyesuaian.
 */
class InventoryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Pesanan')
                    ->searchable(),
                TextColumn::make('material.name')
                    ->label('Bahan')
                    ->searchable(),
                TextColumn::make('batch')
                    ->label('Batch')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('state')
                    ->label('Keadaan')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uom')
                    ->label('Satuan'),
                TextColumn::make('location')
                    ->label('Lokasi')
                    ->placeholder('—'),
            ])
            ->filters([
                // Filter per klien adalah inti view #3: petugas gudang bekerja
                // lintas klien, sehingga perlu mempersempit grid ke satu klien.
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('state')
                    ->label('Keadaan')
                    ->options(InventoryState::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
