<?php

namespace App\Filament\Resources\Assemblies\Tables;

use App\Enums\QcResult;
use App\Models\Assembly;
use App\Models\DefectCategory;
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
 * Daftar perakitan, dengan jumlah output penyusunnya.
 */
class AssembliesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('assembled_at')
                    ->label('Dirakit')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('Total Unit')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('remaining_qc')
                    ->label('Sisa Siap QC')
                    ->state(fn (Assembly $record): int => $record->remainingQuantity())
                    ->badge()
                    ->color(fn (int $state): string => $state === 0 ? 'success' : 'warning')
                    ->formatStateUsing(fn (int $state): string => $state === 0 ? 'Selesai QC' : "{$state} Unit Siap Cek"),
                TextColumn::make('output_entries_count')
                    ->label('Output Penyusun')
                    ->counts('outputEntries')
                    ->numeric(),
                TextColumn::make('assembler.name')
                    ->label('Dirakit Oleh')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('order_id')
                    ->label('Filter Order')
                    ->relationship('order', 'order_number')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('client_id')
                    ->label('Klien')
                    ->relationship('client', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('inspectQc')
                    ->label('Periksa QC')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('primary')
                    ->visible(fn (Assembly $record): bool => $record->remainingQuantity() > 0)
                    ->form([
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
                            ->label('Jumlah yang Diperiksa')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(fn (Assembly $record): int => $record->remainingQuantity())
                            ->default(fn (Assembly $record): int => $record->remainingQuantity())
                            ->required(),
                        Textarea::make('notes')
                            ->label('Catatan Pemeriksaan')
                            ->rows(3),
                    ])
                    ->action(function (Assembly $record, array $data): void {
                        $result = QcResult::from($data['result']);
                        $defectCategory = isset($data['defect_category_id']) ? DefectCategory::find($data['defect_category_id']) : null;

                        app(QcService::class)->recordInspection(
                            assembly: $record,
                            result: $result,
                            quantity: (int) $data['quantity'],
                            defectCategory: $defectCategory,
                            notes: $data['notes'] ?? null,
                        );

                        if ($result === QcResult::PASS) {
                            Notification::make()
                                ->title('Unit Lolos QC')
                                ->body("{$data['quantity']} unit dinyatakan lolos dan masuk Finished Goods.")
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
                                ->body("{$data['quantity']} unit dicatat ke limbah/afkir.")
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('assembled_at', 'desc');
    }
}
