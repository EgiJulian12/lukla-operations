<?php

namespace App\Filament\Resources\ApprovedSamples\Tables;

use App\Enums\WorkScheme;
use App\Filament\Resources\ApprovedSamples\Actions\ConvertToOrderAction;
use App\Filament\Resources\ApprovedSamples\ApprovedSampleResource;
use App\Models\Sample;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabel daftar sample yang telah disetujui (Approved) untuk kepala produksi dan tim sample.
 */
class ApprovedSamplesTable
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
                TextColumn::make('orders.work_scheme')
                    ->label('Skema Order')
                    ->badge()
                    ->placeholder('Belum Ada Order'),
                TextColumn::make('approvedRevision.version_label')
                    ->label('Versi Disetujui')
                    ->badge()
                    ->color('success')
                    ->default('-'),
                TextColumn::make('components_count')
                    ->label('Komponen')
                    ->state(fn (Sample $record): int => (int) ($record->article?->components()->count() ?? 0))
                    ->numeric(),
                TextColumn::make('approvedRevision.approved_at')
                    ->label('Tanggal Disetujui')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('approvedRevision.approver.name')
                    ->label('Disetujui Oleh')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('work_scheme')
                    ->label('Skema Maklon Order')
                    ->options(WorkScheme::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('orders', fn (Builder $q) => $q->where('work_scheme', $data['value']))
                        : $query
                    ),
            ])
            ->recordUrl(fn (Sample $record): string => ApprovedSampleResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Action::make('view')
                    ->label('Buka Detail')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Sample $record): string => ApprovedSampleResource::getUrl('view', ['record' => $record])),
                ConvertToOrderAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
