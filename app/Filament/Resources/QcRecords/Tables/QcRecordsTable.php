<?php

namespace App\Filament\Resources\QcRecords\Tables;

use App\Enums\QcResult;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Riwayat inspeksi QC lintas klien.
 *
 * Tanpa aksi ubah maupun hapus: keputusan QC bersifat final (NFR-005).
 */
class QcRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('inspected_at')
                    ->label('Diperiksa')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable(),
                TextColumn::make('result')
                    ->label('Hasil')
                    ->badge(),
                TextColumn::make('defectCategory.name')
                    ->label('Kategori Cacat')
                    ->placeholder('-'),
                TextColumn::make('quantity')
                    ->label('Unit')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('inspector.name')
                    ->label('Inspector')
                    ->placeholder('-'),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('result')
                    ->label('Hasil')
                    ->options(QcResult::class),
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('inspected_at', 'desc');
    }
}
