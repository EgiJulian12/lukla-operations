<?php

namespace App\Filament\Resources\RateCards\Tables;

use App\Enums\RateType;
use App\Models\RateCard;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar rate card per klien, dengan penanda tarif yang sedang berlaku.
 *
 * Tanpa aksi hapus: tarif lama ditutup dengan effective_to agar riwayatnya
 * tetap tersimpan.
 */
class RateCardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('article.name')
                    ->label('Artikel')
                    ->placeholder('Semua artikel'),
                TextColumn::make('component.name')
                    ->label('Komponen')
                    ->placeholder('Semua komponen'),
                TextColumn::make('rate_type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('rate')
                    ->label('Tarif')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('effective_from')
                    ->label('Berlaku Sejak')
                    ->date()
                    ->sortable(),
                TextColumn::make('effective_to')
                    ->label('Berlaku Sampai')
                    ->date()
                    ->placeholder('Masih berlaku'),
                IconColumn::make('is_currently_effective')
                    ->label('Aktif')
                    ->state(fn (RateCard $record): bool => $record->isCurrentlyEffective())
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('rate_type')
                    ->label('Jenis')
                    ->options(RateType::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('effective_from', 'desc');
    }
}
