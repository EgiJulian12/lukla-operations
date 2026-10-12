<?php

namespace App\Filament\Resources\WasteRecords\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Riwayat sisa potongan, dapat disaring per klien untuk laporan efisiensi (FR-008).
 *
 * Tanpa aksi hapus: catatan sisa potongan ikut diaudit (NFR-005).
 */
class WasteRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Dicatat')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Pesanan')
                    ->searchable(),
                TextColumn::make('material.name')
                    ->label('Bahan')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uom')
                    ->label('Satuan'),
                TextColumn::make('category')
                    ->label('Kategori')
                    ->placeholder('—')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
