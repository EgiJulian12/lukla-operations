<?php

namespace App\Filament\Portal\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Enums\QcResult;
use App\Enums\WorkScheme;
use App\Models\Order;
use App\Services\Order\OrderQuotationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
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
                TextColumn::make('estimated_cost')
                    ->label('Penawaran')
                    ->money('IDR')
                    ->placeholder('Belum ditawar'),
                TextColumn::make('quantity')
                    ->label('Target')
                    ->numeric()
                    ->placeholder('-'),
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
                Action::make('approveQuote')
                    ->label('Setujui Penawaran')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->modalHeading('Konfirmasi Persetujuan Order')
                    ->modalDescription('Pastikan tenggat waktu produksi, skema maklon, dan instruksi khusus sudah sesuai sebelum menyetujui penawaran.')
                    ->visible(fn (Order $record): bool => auth()->user()?->can('approveQuote', $record) ?? false)
                    ->fillForm(fn (Order $record): array => [
                        'quantity' => $record->quantity,
                        'due_date' => $record->due_date?->format('Y-m-d'),
                        'work_scheme' => $record->work_scheme?->value ?? WorkScheme::CMT->value,
                        'notes' => $record->notes,
                    ])
                    ->schema([
                        TextInput::make('quantity')
                            ->label('Jumlah Order (pcs)')
                            ->helperText('Konfirmasi atau sesuaikan jumlah unit pakaian yang dipesan.')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        DatePicker::make('due_date')
                            ->label('Tenggat Produksi')
                            ->helperText('Konfirmasi atau sesuaikan estimasi tenggat waktu selesai produksi.')
                            ->required(),
                        Select::make('work_scheme')
                            ->label('Skema Maklon')
                            ->options(WorkScheme::class)
                            ->required(),
                        Textarea::make('notes')
                            ->label('Catatan Tambahan / Instruksi Khusus')
                            ->placeholder('Tuliskan catatan khusus atau instruksi terkait persetujuan pesanan...')
                            ->rows(3),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(OrderQuotationService::class)->approveQuote(
                            $record,
                            auth()->user(),
                            dueDate: $data['due_date'] ?? null,
                            workScheme: isset($data['work_scheme'])
                                ? ($data['work_scheme'] instanceof WorkScheme ? $data['work_scheme'] : WorkScheme::tryFrom((string) $data['work_scheme']))
                                : null,
                            notes: $data['notes'] ?? null,
                            quantity: isset($data['quantity']) ? (int) $data['quantity'] : null,
                        );

                        Notification::make()
                            ->title('Penawaran Berhasil Disetujui')
                            ->body('Pesanan telah disetujui dan siap diproses lebih lanjut.')
                            ->success()
                            ->send();
                    }),
                Action::make('downloadDesigns')
                    ->label('Unduh Desain')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (Order $record): bool => ! empty($record->design_images))
                    ->url(fn (Order $record): string => route('orders.designs.download', $record))
                    ->openUrlInNewTab(),
                ViewAction::make()
                    ->label('Lihat'),
            ])
            ->poll('30s')
            ->defaultSort('created_at', 'desc');
    }
}
