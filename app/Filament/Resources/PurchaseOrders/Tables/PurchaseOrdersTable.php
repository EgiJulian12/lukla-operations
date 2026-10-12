<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\Finance\PurchaseOrderService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Daftar PO bahan dengan aksi persetujuan (matriks RBAC SRS, Finance).
 *
 * Aksi setujui hanya muncul untuk PO draf dan bagi pengguna yang berwenang,
 * sehingga PO yang sudah disetujui tidak dapat disetujui ulang.
 */
class PurchaseOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('po_number')
                    ->label('Nomor PO')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier')
                    ->label('Pemasok')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('ordered_at')
                    ->label('Tanggal Pesan')
                    ->date()
                    ->placeholder('-')
                    ->sortable(),
                TextColumn::make('expected_at')
                    ->label('Perkiraan Datang')
                    ->date()
                    ->placeholder('-'),
                TextColumn::make('items_count')
                    ->label('Baris Bahan')
                    ->counts('items')
                    ->numeric(),
                TextColumn::make('total')
                    ->label('Nilai PO')
                    ->state(fn (PurchaseOrder $record): float => $record->totalAmount())
                    ->money('IDR'),
                TextColumn::make('approver.name')
                    ->label('Disetujui Oleh')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(PurchaseOrderStatus::class),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseOrder $record): bool => (bool) auth()->user()?->can('approve', $record))
                    ->action(fn (PurchaseOrder $record) => app(PurchaseOrderService::class)->approve($record))
                    ->successNotificationTitle('PO disetujui'),
                EditAction::make()
                    ->label('Ubah'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
