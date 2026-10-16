<?php

namespace App\Filament\Resources\Samples\Tables;

use App\Enums\SampleStatus;
use App\Filament\Resources\ApprovedSamples\Actions\ConvertToOrderAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar sample beserta aksi konversinya ke order massal (FR-004).
 */
class SamplesTable
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
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('revisions_count')
                    ->label('Revisi')
                    ->counts('revisions')
                    ->numeric(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SampleStatus::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat Detail')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray'),
                ConvertToOrderAction::make()
                    ->label('Jadikan Order'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
