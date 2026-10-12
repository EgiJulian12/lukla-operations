<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\WorkScheme;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar order massal lintas klien, dengan tautan ke revisi sample asalnya.
 */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nomor Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('work_scheme')
                    ->label('Skema')
                    ->badge(),
                TextColumn::make('sample.code')
                    ->label('Sample')
                    ->placeholder('-')
                    ->description(fn ($record): ?string => $record->sampleRevision !== null
                        ? 'Revisi v'.$record->sampleRevision->version
                        : null),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('due_date')
                    ->label('Tenggat')
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('work_scheme')
                    ->label('Skema')
                    ->options(WorkScheme::class),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(OrderStatus::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
