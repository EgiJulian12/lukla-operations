<?php

namespace App\Filament\Portal\Resources\QcRecords\Tables;

use App\Enums\QcResult;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Riwayat hasil QC milik klien, dapat disaring per hasil dan per pesanan.
 *
 * Baris sudah terpotong ke klien yang login oleh global scope, jadi klien tidak
 * pernah melihat hasil QC order klien lain.
 */
class PortalQcRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('Pesanan')
                    ->searchable(),
                TextColumn::make('result')
                    ->label('Hasil')
                    ->badge(),
                TextColumn::make('defectCategory.name')
                    ->label('Kategori Cacat')
                    ->placeholder('-'),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric(),
                TextColumn::make('inspected_at')
                    ->label('Diperiksa')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->limit(40),
            ])
            ->filters([
                SelectFilter::make('result')
                    ->label('Hasil')
                    ->options(QcResult::class),
                SelectFilter::make('order_id')
                    ->label('Pesanan')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->preload(),
            ])
            ->poll('30s')
            ->defaultSort('inspected_at', 'desc');
    }
}
