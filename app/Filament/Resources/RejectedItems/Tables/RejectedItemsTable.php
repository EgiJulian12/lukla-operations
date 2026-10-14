<?php

namespace App\Filament\Resources\RejectedItems\Tables;

use App\Enums\QcResult;
use App\Models\DefectCategory;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Tabel barang yang ditolak / afkir total oleh QC (SCRAP).
 */
class RejectedItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('inspected_at')
                    ->label('Tgl Ditolak')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('defectCategory.name')
                    ->label('Alasan / Kategori Cacat')
                    ->badge()
                    ->color('danger')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Jumlah Afkir (Unit)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('inspector.name')
                    ->label('Diperiksa Oleh')
                    ->placeholder('-'),
                TextColumn::make('notes')
                    ->label('Catatan Penolakan')
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('order_id')
                    ->label('Filter Order')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('defect_category_id')
                    ->label('Kategori Cacat')
                    ->options(fn (): array => DefectCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->modifyQueryUsing(fn ($query) => $query->where('result', QcResult::SCRAP->value))
            ->defaultSort('inspected_at', 'desc');
    }
}
