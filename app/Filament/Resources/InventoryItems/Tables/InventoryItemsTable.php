<?php

namespace App\Filament\Resources\InventoryItems\Tables;

use App\Enums\DispatchStatus;
use App\Enums\InventoryState;
use App\Models\InventoryItem;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Grid inventaris multi-tenant — SRS view #3.
 *
 * Tidak ada aksi hapus: stok tidak pernah dihapus, dan InventoryItemPolicy
 * menolaknya. Koreksi jumlah dilakukan lewat pergerakan penyesuaian.
 */
class InventoryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tag_id')
                    ->label('Tag ID')
                    ->badge()
                    ->color('gray')
                    ->searchable(query: function ($query, string $search) {
                        return $query->where('id', 'like', "%{$search}%");
                    })
                    ->copyable()
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderBy('id', $direction);
                    }),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Pesanan')
                    ->searchable(),
                TextColumn::make('material.name')
                    ->label('Bahan')
                    ->searchable(),
                TextColumn::make('batch')
                    ->label('Batch')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('state')
                    ->label('Keadaan')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('uom')
                    ->label('Satuan'),
                TextColumn::make('location')
                    ->label('Lokasi')
                    ->placeholder('—'),
                TextColumn::make('dispatch_status')
                    ->label('Status Pengeluaran')
                    ->badge()
                    ->state(function (InventoryItem $record): string {
                        if ($record->isReceivedByProduction()) {
                            return 'Sudah Keluar (Diterima Produksi)';
                        }

                        if ($record->isDispatched()) {
                            return 'Sedang Dikirim ke Produksi';
                        }

                        return 'Tersedia di Gudang';
                    })
                    ->color(function (InventoryItem $record): string {
                        if ($record->isReceivedByProduction()) {
                            return 'success';
                        }

                        if ($record->isDispatched()) {
                            return 'warning';
                        }

                        return 'gray';
                    }),
            ])
            ->filters([
                SelectFilter::make('dispatch_status_filter')
                    ->label('Status Keberadaan Barang')
                    ->options([
                        'in_warehouse' => 'Masih di Gudang (Belum Dikirim)',
                        'dispatched' => 'Sedang Dikirim ke Produksi',
                        'received' => 'Sudah Keluar (Diterima Produksi)',
                        'all' => 'Semua Barang',
                    ])
                    ->default('in_warehouse')
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (blank($value) || $value === 'all') {
                            return $query;
                        }

                        return match ($value) {
                            'in_warehouse' => $query->whereDoesntHave('dispatches', function ($q) {
                                $q->whereIn('status', [
                                    DispatchStatus::DISPATCHED,
                                    DispatchStatus::PENDING_DELIVERY,
                                    DispatchStatus::RECEIVED,
                                    DispatchStatus::IDLE,
                                    DispatchStatus::IN_CUTTING,
                                    DispatchStatus::COMPLETED,
                                ]);
                            }),
                            'dispatched' => $query->whereHas('dispatches', function ($q) {
                                $q->whereIn('status', [
                                    DispatchStatus::DISPATCHED,
                                    DispatchStatus::PENDING_DELIVERY,
                                ]);
                            }),
                            'received' => $query->whereHas('dispatches', function ($q) {
                                $q->whereIn('status', [
                                    DispatchStatus::RECEIVED,
                                    DispatchStatus::IDLE,
                                    DispatchStatus::IN_CUTTING,
                                    DispatchStatus::COMPLETED,
                                ]);
                            }),
                            default => $query,
                        };
                    }),

                // Filter per klien adalah inti view #3: petugas gudang bekerja
                // lintas klien, sehingga perlu mempersempit grid ke satu klien.
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('state')
                    ->label('Keadaan')
                    ->options(InventoryState::class),
            ])
            ->recordActions([
                Action::make('print_tag')
                    ->label('Cetak Label')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->color('warning')
                    ->url(fn (InventoryItem $record): string => route('inventory-items.print-tag', ['item' => $record]))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->label('Ubah')
                    ->visible(fn (InventoryItem $record): bool => ! $record->isReceivedByProduction()),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
