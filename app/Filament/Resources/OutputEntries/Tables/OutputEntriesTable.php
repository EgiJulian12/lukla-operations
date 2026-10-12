<?php

namespace App\Filament\Resources\OutputEntries\Tables;

use App\Enums\OutputStatus;
use App\Models\OutputEntry;
use App\Services\Production\ProductionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar output 30 menit dengan aksi verifikasi mandor (FR-012).
 *
 * Verifikasi dan penolakan hanya muncul untuk output yang masih menunggu
 * verifikasi, dan bagi pengguna yang berwenang memverifikasi.
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
                    ->label('Line'),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable(),
                TextColumn::make('component.name')
                    ->label('Komponen'),
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
                Action::make('verify')
                    ->label('Verifikasi')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (OutputEntry $record): bool => $record->status === OutputStatus::SUBMITTED
                        && (bool) auth()->user()?->can('verify', $record))
                    ->action(fn (OutputEntry $record) => app(ProductionService::class)
                        ->verifyOutput($record, auth()->user()))
                    ->successNotificationTitle('Output terverifikasi'),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(fn (OutputEntry $record): bool => $record->status === OutputStatus::SUBMITTED
                        && (bool) auth()->user()?->can('reject', $record))
                    ->form([
                        Textarea::make('reason')
                            ->label('Alasan Penolakan')
                            ->required(),
                    ])
                    ->action(fn (OutputEntry $record, array $data) => app(ProductionService::class)
                        ->rejectOutput($record, $data['reason']))
                    ->successNotificationTitle('Output ditolak'),
            ])
            ->defaultSort('interval_start', 'desc');
    }
}
