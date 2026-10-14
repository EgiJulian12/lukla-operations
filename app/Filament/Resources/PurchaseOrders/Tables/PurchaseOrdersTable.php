<?php

namespace App\Filament\Resources\PurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\Finance\PurchaseOrderService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\Alignment;
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
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('order.order_number')
                    ->label('Nomor Pesanan')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('requester.name')
                    ->label('Diajukan Oleh')
                    ->searchable()
                    ->placeholder('-'),
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
                Action::make('viewDetails')
                    ->label('Rincian')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->modalWidth('4xl')
                    ->modalHeading(fn (PurchaseOrder $record): string => "Rincian Pengajuan PO — {$record->po_number}")
                    ->modalDescription(fn (PurchaseOrder $record): string => "Order: {$record->order?->order_number} • Pemasok: {$record->supplier} • Diajukan: {$record->requester?->name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Catatan Pengajuan')
                            ->placeholder('Tidak ada catatan.'),
                        RepeatableEntry::make('items')
                            ->label('Daftar Bahan / Barang yang Dipesan')
                            ->table([
                                TableColumn::make('Bahan / Item'),
                                TableColumn::make('Jumlah')->alignment(Alignment::End),
                                TableColumn::make('Harga Satuan')->alignment(Alignment::End),
                                TableColumn::make('Subtotal')->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('material.name')
                                    ->weight('bold'),
                                TextEntry::make('quantity')
                                    ->numeric()
                                    ->formatStateUsing(fn ($state, $record): string => number_format((float) $state, 2).' '.($record->material?->uom ?? 'pcs')),
                                TextEntry::make('unit_price')
                                    ->money('IDR'),
                                TextEntry::make('subtotal')
                                    ->state(fn ($record): float => $record->lineTotal())
                                    ->money('IDR')
                                    ->weight('bold'),
                            ]),
                    ]),
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
