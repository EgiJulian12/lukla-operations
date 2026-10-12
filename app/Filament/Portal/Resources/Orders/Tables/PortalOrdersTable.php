<?php

namespace App\Filament\Portal\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Enums\QcResult;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar pesanan klien dengan progres produksi dan hasil QC-nya.
 *
 * Angka progres berasal dari output yang sudah diverifikasi mandor, sehingga
 * klien melihat pekerjaan yang benar-benar diakui, bukan laporan mentah.
 */
class PortalOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nomor Pesanan')
                    ->searchable(),
                TextColumn::make('work_scheme')
                    ->label('Skema')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Target')
                    ->numeric(),
                TextColumn::make('verified_output')
                    ->label('Terverifikasi')
                    ->state(fn (Order $record): int => (int) $record->outputEntries()
                        ->where('status', OutputStatus::VERIFIED->value)
                        ->sum('quantity'))
                    ->numeric(),
                TextColumn::make('passed_units')
                    ->label('Lolos QC')
                    ->state(fn (Order $record): int => (int) $record->qcRecords()
                        ->where('result', QcResult::PASS->value)
                        ->sum('quantity'))
                    ->numeric(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('due_date')
                    ->label('Tenggat')
                    ->date()
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(OrderStatus::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Lihat'),
            ])
            ->poll('30s')
            ->defaultSort('created_at', 'desc');
    }
}
