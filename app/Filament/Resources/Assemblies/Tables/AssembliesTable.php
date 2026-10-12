<?php

namespace App\Filament\Resources\Assemblies\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar perakitan, dengan jumlah output penyusunnya.
 */
class AssembliesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('assembled_at')
                    ->label('Dirakit')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Unit')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('output_entries_count')
                    ->label('Output Penyusun')
                    ->counts('outputEntries')
                    ->numeric(),
                TextColumn::make('assembler.name')
                    ->label('Dirakit Oleh')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('assembled_at', 'desc');
    }
}
