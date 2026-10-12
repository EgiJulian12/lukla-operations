<?php

namespace App\Filament\Resources\InventoryMovements\Tables;

use App\Enums\MovementType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Riwayat pergerakan stok.
 *
 * Tanpa aksi ubah atau hapus: pergerakan bersifat append-only agar riwayat
 * tetap dapat diaudit (NFR-005).
 */
class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recorded_at')
                    ->label('Waktu')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Pesanan')
                    ->searchable(),
                TextColumn::make('inventoryItem.material.name')
                    ->label('Bahan')
                    ->searchable(),
                TextColumn::make('movement_type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Perubahan')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reference')
                    ->label('Referensi')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('creator.name')
                    ->label('Dicatat Oleh')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('movement_type')
                    ->label('Jenis Pergerakan')
                    ->options(MovementType::class),
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('recorded_at', 'desc');
    }
}
