<?php

namespace App\Filament\Resources\PayrollPeriods\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Rincian upah borongan per penjahit pada sebuah periode (FR-015).
 *
 * Sengaja tanpa aksi apa pun: baris dibuat oleh perhitungan payroll, bukan oleh
 * penyuntingan manual, sehingga angka yang dibayarkan selalu berasal dari
 * output terverifikasi.
 */
class PayrollEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'Rincian Upah Borongan';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Penjahit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('total_pieces')
                    ->label('Total Pcs')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sewing_amount')
                    ->label('Ongkos Jahit')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('meal_allowance_amount')
                    ->label('Uang Makan')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR')
                    ->weight('bold')
                    ->sortable(),
            ])
            ->defaultSort('total_amount', 'desc');
    }
}
