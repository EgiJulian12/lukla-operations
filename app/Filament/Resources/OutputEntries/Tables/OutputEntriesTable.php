<?php

namespace App\Filament\Resources\OutputEntries\Tables;

use App\Enums\OutputStatus;
use App\Filament\Resources\OutputEntries\Actions\RejectOutputAction;
use App\Filament\Resources\OutputEntries\Actions\ResubmitOutputAction;
use App\Filament\Resources\OutputEntries\Actions\VerifyOutputAction;
use App\Models\OutputEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar output 30 menit dengan aksi verifikasi mandor (FR-012).
 *
 * Verifikasi dan penolakan hanya muncul untuk output yang masih menunggu
 * verifikasi, dan bagi pengguna yang berwenang memverifikasi. Kedua aksinya
 * dipakai bersama halaman verifikasi per order, sehingga perilakunya sama di
 * mana pun mandor bertemu baris output.
 */
class OutputEntriesTable
{
    public static function configure(Table $table): Table
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
                TextColumn::make('productionLine.name')
                    ->label('Line')
                    ->placeholder('-'),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order / Proyek')
                    ->description(fn (OutputEntry $record): ?string => $record->order?->article?->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('component.name')
                    ->label('Komponen')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Pcs')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('verifier.name')
                    ->label('Diverifikasi Oleh')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(OutputStatus::class),
                SelectFilter::make('production_line_id')
                    ->label('Line')
                    ->relationship('productionLine', 'name')
                    ->preload(),
            ])
            ->recordActions([
                VerifyOutputAction::make(),
                RejectOutputAction::make(),
                ResubmitOutputAction::make(),
            ])
            ->defaultSort('interval_start', 'desc');
    }
}
