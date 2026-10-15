<?php

namespace App\Filament\Portal\Resources\Samples\Tables;

use App\Enums\SampleStatus;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PortalSamplesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Kode Sample')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Judul Sample')
                    ->searchable(),
                TextColumn::make('article.name')
                    ->label('Artikel')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('revisions_count')
                    ->label('Revisi')
                    ->counts('revisions')
                    ->numeric(),
                TextColumn::make('created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SampleStatus::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat Detail & Hasil')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('primary'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
