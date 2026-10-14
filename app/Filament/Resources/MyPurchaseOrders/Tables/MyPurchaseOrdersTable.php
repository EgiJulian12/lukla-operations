<?php

namespace App\Filament\Resources\MyPurchaseOrders\Tables;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\Finance\PurchaseOrderService;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MyPurchaseOrdersTable
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
                    ->label('Nomor Order')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('requester.name')
                    ->label('Diajukan Oleh')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('supplier')
                    ->label('Pemasok / Supplier')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status Pengajuan')
                    ->badge(),
                TextColumn::make('ordered_at')
                    ->label('Tgl Pengajuan')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('items_count')
                    ->label('Jumlah Barang')
                    ->counts('items')
                    ->numeric(),
                TextColumn::make('total')
                    ->label('Estimasi Nilai')
                    ->state(fn (PurchaseOrder $record): float => $record->totalAmount())
                    ->money('IDR'),
                TextColumn::make('approver.name')
                    ->label('Disetujui Oleh')
                    ->placeholder('Menunggu Persetujuan')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
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
                    ->button()
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
                    ->button()
                    ->requiresConfirmation()
                    ->modalHeading(fn (PurchaseOrder $record): string => "Setujui PO — {$record->po_number}")
                    ->modalDescription('Pastikan rincian bahan dan estimasi harga sudah sesuai sebelum menyetujui pengajuan PO ini.')
                    ->visible(fn (PurchaseOrder $record): bool => (bool) auth()->user()?->can('approve', $record))
                    ->action(function (PurchaseOrder $record) {
                        app(PurchaseOrderService::class)->approve($record);
                        Notification::make()
                            ->title('PO Berhasil Disetujui!')
                            ->body("Pengajuan PO {$record->po_number} telah disetujui.")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
