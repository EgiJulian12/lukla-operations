<?php

namespace App\Filament\Resources\Repairs\Tables;

use App\Enums\QcResult;
use App\Enums\RepairStatus;
use App\Models\DefectCategory;
use App\Models\Order;
use App\Models\Repair;
use App\Models\User;
use App\Services\Quality\QcService;
use App\Services\Quality\RepairManager;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabel alur kerja perbaikan (Repair) barang cacat QC (FR-014, BR 4.6).
 */
class RepairsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Tgl Cacat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('qcRecord.order.order_number')
                    ->label('Order')
                    ->searchable(),
                TextColumn::make('qcRecord.client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('qcRecord.defectCategory.name')
                    ->label('Kategori Cacat')
                    ->badge()
                    ->color('danger'),
                TextColumn::make('qcRecord.quantity')
                    ->label('Unit')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('assignedRepairer.name')
                    ->label('Petugas Perbaikan')
                    ->placeholder('-'),
                TextColumn::make('qcRecord.notes')
                    ->label('Catatan QC')
                    ->placeholder('-')
                    ->limit(25),
                TextColumn::make('notes')
                    ->label('Catatan Perbaikan')
                    ->placeholder('-')
                    ->limit(25)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('completed_at')
                    ->label('Selesai Perbaikan')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('order_id')
                    ->label('Filter Order')
                    ->options(fn (): array => Order::query()->latest()->pluck('order_number', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('qcRecord', fn ($q) => $q->where('order_id', $data['value']))
                        : $query
                    )
                    ->searchable(),
                SelectFilter::make('status')
                    ->label('Status Tiket')
                    ->options(RepairStatus::class),
                SelectFilter::make('assigned_repairer_id')
                    ->label('Petugas Perbaikan')
                    ->relationship('assignedRepairer', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('defect_category_id')
                    ->label('Kategori Cacat')
                    ->options(fn (): array => DefectCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('qcRecord', fn ($q) => $q->where('defect_category_id', $data['value']))
                        : $query
                    )
                    ->searchable(),
                Filter::make('repair_date')
                    ->label('Tanggal Cacat')
                    ->form([
                        DatePicker::make('date')
                            ->label('Tanggal Cacat')
                            ->placeholder('Pilih tanggal...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['date'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', $date),
                        );
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->actions([
                // Aksi Kepala Produksi: Menugaskan petugas perbaikan
                Action::make('assignRepairer')
                    ->label('Tugaskan')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('info')
                    ->visible(fn (Repair $record): bool => in_array($record->status, [RepairStatus::PENDING, RepairStatus::IN_REPAIR], true))
                    ->form([
                        Select::make('assigned_repairer_id')
                            ->label('Petugas Perbaikan')
                            ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                        Textarea::make('notes')
                            ->label('Instruksi Perbaikan')
                            ->rows(3),
                    ])
                    ->action(function (Repair $record, array $data): void {
                        $repairer = User::findOrFail($data['assigned_repairer_id']);
                        app(RepairManager::class)->assignRepairer($record, $repairer, $data['notes'] ?? null);

                        Notification::make()
                            ->title('Perbaikan Dimulai')
                            ->body("Ditugaskan kepada {$repairer->name}.")
                            ->success()
                            ->send();
                    }),

                // Aksi Kepala Produksi: Selesaikan perbaikan dan ubah status jadi Siap Dicek Ulang
                Action::make('markCompleted')
                    ->label('Selesai Perbaikan')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Repair $record): bool => in_array($record->status, [RepairStatus::PENDING, RepairStatus::IN_REPAIR], true))
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Selesai Perbaikan')
                    ->modalDescription('Tandai perbaikan ini selesai? Status barang akan berubah menjadi "Siap Dicek Ulang" dan otomatis masuk kembali ke antrean QC.')
                    ->form([
                        Textarea::make('notes')
                            ->label('Catatan Hasil Perbaikan')
                            ->rows(3),
                    ])
                    ->action(function (Repair $record, array $data): void {
                        app(RepairManager::class)->markCompleted($record, $data['notes'] ?? null);

                        Notification::make()
                            ->title('Perbaikan Selesai')
                            ->body('Status barang berubah menjadi Siap Dicek Ulang oleh QC.')
                            ->success()
                            ->send();
                    }),

                // Aksi QC: Lakukan Inspeksi Ulang terhadap barang yang sudah diperbaiki
                Action::make('reinspect')
                    ->label('Inspeksi Ulang')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('primary')
                    ->visible(fn (Repair $record): bool => $record->status === RepairStatus::COMPLETED)
                    ->form([
                        Select::make('result')
                            ->label('Hasil QC Ulang')
                            ->options([
                                QcResult::PASS->value => 'Lolos (Pass)',
                                QcResult::REPAIR->value => 'Perlu Perbaikan Ulang (Repair)',
                                QcResult::SCRAP->value => 'Afkir / Reject (Scrap)',
                            ])
                            ->default(QcResult::PASS->value)
                            ->required()
                            ->live(),
                        Select::make('defect_category_id')
                            ->label('Kategori Cacat Baru')
                            ->options(fn (): array => DefectCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                            ->visible(fn (Get $get): bool => in_array($get('result'), [QcResult::REPAIR->value, QcResult::SCRAP->value], true))
                            ->required(fn (Get $get): bool => in_array($get('result'), [QcResult::REPAIR->value, QcResult::SCRAP->value], true)),
                        Textarea::make('notes')
                            ->label('Catatan QC Ulang')
                            ->rows(3),
                    ])
                    ->action(function (Repair $record, array $data): void {
                        $assembly = $record->qcRecord->assembly;
                        $result = QcResult::from($data['result']);
                        $defectCategory = isset($data['defect_category_id']) ? DefectCategory::find($data['defect_category_id']) : null;

                        if ($result === QcResult::PASS) {
                            if ($assembly !== null) {
                                app(QcService::class)->recordInspection(
                                    assembly: $assembly,
                                    result: QcResult::PASS,
                                    quantity: $record->qcRecord->quantity,
                                    notes: 'Lolos QC Ulang setelah perbaikan. '.($data['notes'] ?? ''),
                                );
                            }
                            app(RepairManager::class)->markVerified($record);

                            Notification::make()
                                ->title('Barang Lolos QC Ulang')
                                ->body('Unit berhasil lolos dan masuk ke Finished Goods.')
                                ->success()
                                ->send();
                        } elseif ($result === QcResult::REPAIR) {
                            $record->update([
                                'status' => RepairStatus::PENDING,
                                'notes' => ($record->notes ? $record->notes."\n" : '').'Perlu perbaikan ulang: '.($data['notes'] ?? ''),
                            ]);

                            Notification::make()
                                ->title('Perlu Perbaikan Ulang')
                                ->body('Barang dikembalikan ke status Menunggu Perbaikan.')
                                ->warning()
                                ->send();
                        } else {
                            if ($assembly !== null) {
                                app(QcService::class)->recordInspection(
                                    assembly: $assembly,
                                    result: QcResult::SCRAP,
                                    quantity: $record->qcRecord->quantity,
                                    defectCategory: $defectCategory,
                                    notes: 'Di-reject setelah perbaikan gagal. '.($data['notes'] ?? ''),
                                );
                            }
                            $record->update(['status' => RepairStatus::VERIFIED]);

                            Notification::make()
                                ->title('Barang Di-Reject / Scrap')
                                ->body('Barang dicatat ke afkir/waste_records.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
