<?php

namespace App\Filament\Portal\Resources\ApprovedSamples\Tables;

use App\Models\Sample;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PortalApprovedSamplesTable
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
                TextColumn::make('approvedRevision.version_label')
                    ->label('Versi Disetujui')
                    ->badge()
                    ->color('success')
                    ->default('-'),
                TextColumn::make('approvedRevision.approved_at')
                    ->label('Tanggal Disetujui')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('components_count')
                    ->label('Komponen')
                    ->state(fn (Sample $record): int => (int) ($record->article?->components()->count() ?? 0))
                    ->numeric(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat Detail Sample')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('primary'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
