<?php

namespace App\Filament\Resources\OverheadAllocations\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Daftar alokasi overhead per unit tiap bulan.
 */
class OverheadAllocationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period_month')
                    ->label('Bulan')
                    ->date('F Y')
                    ->sortable(),
                TextColumn::make('total_overhead')
                    ->label('Total Overhead')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('total_units')
                    ->label('Total Unit')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('overhead_per_unit')
                    ->label('Overhead per Unit')
                    ->numeric(decimalPlaces: 4)
                    ->prefix('Rp ')
                    ->weight('bold')
                    ->sortable(),
            ])
            ->defaultSort('period_month', 'desc');
    }
}
