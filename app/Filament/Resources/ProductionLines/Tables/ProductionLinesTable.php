<?php

namespace App\Filament\Resources\ProductionLines\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Daftar line beserta jumlah penjahit yang sedang bertugas.
 */
class ProductionLinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('capacity')
                    ->label('Kapasitas')
                    ->numeric()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('active_assignments_count')
                    ->label('Penjahit Bertugas')
                    ->counts('activeAssignments')
                    ->numeric(),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah'),
                DeleteAction::make()
                    ->label('Hapus'),
            ])
            ->defaultSort('code');
    }
}
