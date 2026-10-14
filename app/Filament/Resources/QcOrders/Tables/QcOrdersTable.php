<?php

namespace App\Filament\Resources\QcOrders\Tables;

use App\Enums\QcResult;
use App\Models\Assembly;
use App\Models\DefectCategory;
use App\Models\Order;
use App\Services\Quality\QcService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Tabel antrean pesanan untuk QC (Order-Centric Inspection Hub).
 */
class QcOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->label('Nomor Order')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Target')
                    ->numeric()
                    ->formatStateUsing(fn (int $state): string => "{$state} pcs")
                    ->sortable(),
                TextColumn::make('qc_ready')
                    ->label('Status Kesiapan')
                    ->state(fn (Order $record): int => $record->qcReadyQuantity())
                    ->badge()
                    ->color(function (int $state, Order $record): string {
                        if ($state > 0) {
                            return 'warning';
                        }
                        if ($record->qcPassedQuantity() >= $record->quantity) {
                            return 'success';
                        }

                        return 'gray';
                    })
                    ->formatStateUsing(function (int $state, Order $record): string {
                        if ($state > 0) {
                            return "Siap QC ({$state} unit)";
                        }
                        if ($record->qcPassedQuantity() >= $record->quantity) {
                            return 'Selesai QC';
                        }

                        return 'Menunggu Perakitan';
                    }),
                TextColumn::make('qc_passed')
                    ->label('Lolos')
                    ->state(fn (Order $record): int => $record->qcPassedQuantity())
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn (int $state): string => "{$state} Lolos"),
                TextColumn::make('qc_repair')
                    ->label('Perbaikan')
                    ->state(fn (Order $record): int => $record->qcRepairQuantity())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn (int $state): string => "{$state} Repair"),
                TextColumn::make('qc_scrap')
                    ->label('Ditolak')
                    ->state(fn (Order $record): int => $record->qcScrapQuantity())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->formatStateUsing(fn (int $state): string => "{$state} Afkir"),
                TextColumn::make('qc_progress')
                    ->label('Progress')
                    ->state(fn (Order $record): float => $record->qcProgressPercentage())
                    ->badge()
                    ->color(fn (float $state): string => $state >= 100 ? 'success' : ($state >= 50 ? 'info' : 'gray'))
                    ->formatStateUsing(fn (float $state): string => "{$state}%"),
                TextColumn::make('due_date')
                    ->label('Tenggat')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                // Aksi Utama: Buka antrean dan periksa barang dari order ini
                Action::make('inspectOrder')
                    ->label('Periksa QC')
                    ->icon(Heroicon::OutlinedPlay)
                    ->color('primary')
                    ->button()
                    ->visible(fn (Order $record): bool => $record->qcReadyQuantity() > 0)
                    ->modalHeading(fn (Order $record): string => "Pemeriksaan QC — {$record->order_number}")
                    ->modalDescription(fn (Order $record): string => "Klien: {$record->client?->name} · Target: {$record->quantity} pcs · Siap Dicek: {$record->qcReadyQuantity()} unit")
                    ->form([
                        Select::make('assembly_id')
                            ->label('Perakitan yang Siap Dicek')
                            ->options(function (Order $record): array {
                                return $record->assemblies()
                                    ->pendingQc()
                                    ->latest('assembled_at')
                                    ->get()
                                    ->mapWithKeys(fn (Assembly $assembly): array => [
                                        $assembly->getKey() => sprintf(
                                            'Perakitan #%d · %d unit siap cek (total: %d) · %s',
                                            $assembly->getKey(),
                                            $assembly->remainingQuantity(),
                                            $assembly->quantity,
                                            $assembly->assembled_at->format('d/m H:i'),
                                        ),
                                    ])
                                    ->all();
                            })
                            ->live()
                            ->afterStateUpdated(function ($set, $state) {
                                if ($state && ($assembly = Assembly::find($state))) {
                                    $set('quantity', $assembly->remainingQuantity());
                                }
                            })
                            ->required(),
                        Select::make('result')
                            ->label('Hasil QC')
                            ->options([
                                QcResult::PASS->value => 'Lolos (Pass)',
                                QcResult::REPAIR->value => 'Perlu Perbaikan (Repair)',
                                QcResult::SCRAP->value => 'Afkir / Reject (Scrap)',
                            ])
                            ->default(QcResult::PASS->value)
                            ->required()
                            ->live(),
                        Select::make('defect_category_id')
                            ->label('Kategori Cacat')
                            ->options(fn (): array => DefectCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->visible(fn (Get $get): bool => in_array($get('result'), [QcResult::REPAIR->value, QcResult::SCRAP->value], true))
                            ->required(fn (Get $get): bool => in_array($get('result'), [QcResult::REPAIR->value, QcResult::SCRAP->value], true)),
                        TextInput::make('quantity')
                            ->label('Jumlah yang Diperiksa (unit)')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Catatan Pemeriksaan')
                            ->rows(3),
                    ])
                    ->action(function (Order $record, array $data): void {
                        $assembly = Assembly::findOrFail($data['assembly_id']);
                        $result = QcResult::from($data['result']);
                        $defectCategory = isset($data['defect_category_id']) ? DefectCategory::find($data['defect_category_id']) : null;

                        app(QcService::class)->recordInspection(
                            assembly: $assembly,
                            result: $result,
                            quantity: (int) $data['quantity'],
                            defectCategory: $defectCategory,
                            notes: $data['notes'] ?? null,
                        );

                        if ($result === QcResult::PASS) {
                            Notification::make()
                                ->title('Unit Lolos QC')
                                ->body("{$data['quantity']} unit berhasil lolos ke Finished Goods.")
                                ->success()
                                ->send();
                        } elseif ($result === QcResult::REPAIR) {
                            Notification::make()
                                ->title('Tiket Perbaikan Dibuat')
                                ->body("{$data['quantity']} unit dialihkan ke Halaman Perbaikan (Repair).")
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Unit Di-Reject / Scrap')
                                ->body("{$data['quantity']} unit dicatat ke daftar Barang Ditolak.")
                                ->danger()
                                ->send();
                        }
                    }),

                // Aksi Cepat: Lihat Perbaikan Order Ini
                Action::make('viewRepairs')
                    ->label('Repair')
                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                    ->color('warning')
                    ->visible(fn (Order $record): bool => $record->qcRepairQuantity() > 0)
                    ->url(fn (Order $record): string => url("/admin/repairs?tableFilters[order_id][value]={$record->getKey()}")),

                // Aksi Cepat: Lihat Barang Ditolak Order Ini (di Tab Scrap Log QC)
                Action::make('viewScraps')
                    ->label('Afkir')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Order $record): bool => $record->qcScrapQuantity() > 0)
                    ->url(fn (Order $record): string => url("/admin/qc-records?activeTab=scrap&tableFilters[order_id][value]={$record->getKey()}")),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
