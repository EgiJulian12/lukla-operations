<?php

namespace App\Filament\Resources\OutputReviews\RelationManagers;

use App\Filament\Resources\OutputEntries\Actions\RejectOutputAction;
use App\Filament\Resources\OutputEntries\Actions\VerifyOutputAction;
use App\Models\OutputEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Output 30 menit milik satu order, tempat mandor memeriksa dan memutuskan.
 *
 * Aksi verifikasi dan penolakan di sini adalah aksi yang sama dengan yang dipakai
 * tabel output mandor, sehingga baris yang ditolak di halaman ini juga membuka
 * kembali tugas penjahitnya di menu Output 30 Menit milik penjahit.
 */
class OutputEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'outputEntries';

    protected static ?string $title = 'Output 30 Menit';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('interval_start')
                    ->label('Interval')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OutputEntry $record): string => $record->interval_end->format('H:i'))
                    ->sortable(),
                TextColumn::make('operator.name')
                    ->label('Penjahit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('component.name')
                    ->label('Komponen'),
                TextColumn::make('quantity')
                    ->label('Pcs')
                    ->numeric()
                    ->sortable(),
                // Line boleh kosong sejak penugasan ditetapkan per komponen, jadi
                // baris tanpa line adalah keadaan yang sah, bukan data hilang.
                TextColumn::make('productionLine.name')
                    ->label('Line')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('verifier.name')
                    ->label('Diverifikasi Oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                VerifyOutputAction::make(),
                RejectOutputAction::make(),
            ])
            ->defaultSort('interval_start', 'desc')
            ->emptyStateHeading('Belum ada output')
            ->emptyStateDescription('Laporan 30 menit penjahit untuk order ini akan muncul di sini.')
            ->paginated([10, 25, 50]);
    }
}
