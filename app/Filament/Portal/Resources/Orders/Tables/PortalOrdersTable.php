<?php

namespace App\Filament\Portal\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Enums\QcResult;
use App\Models\Order;
use App\Services\Order\OrderQuotationService;
use App\Services\Order\QuoteDocumentRenderer;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

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
                Action::make('viewQuote')
                    ->label('Dokumen Penawaran')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('info')
                    ->visible(fn (Order $record): bool => (bool) $record->quoted_at && (float) $record->estimated_cost > 0)
                    ->modalHeading(fn (Order $record): string => "Dokumen Penawaran — {$record->order_number}")
                    ->modalContent(fn (Order $record): HtmlString => new HtmlString(QuoteDocumentRenderer::renderHtml($record)))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('4xl'),
                Action::make('approveQuote')
                    ->label('Setujui Penawaran')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->modalHeading('Konfirmasi Persetujuan Penawaran')
                    ->modalDescription('Tinjau rincian penawaran biaya dan jadwal dari owner sebelum menyetujui.')
                    ->visible(fn (Order $record): bool => auth()->user()?->can('approveQuote', $record) ?? false)
                    ->fillForm(fn (Order $record): array => [
                        'notes' => $record->notes,
                    ])
                    ->schema([
                        Section::make('Rincian Penawaran Owner')
                            ->description('Informasi biaya dan jadwal produksi yang disanggupi oleh pabrik.')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('estimated_cost')
                                    ->label('Perkiraan Biaya')
                                    ->money('IDR')
                                    ->placeholder('-')
                                    ->dehydrated(false),
                                TextEntry::make('quoted_at')
                                    ->label('Waktu Penawaran')
                                    ->dateTime()
                                    ->placeholder('-')
                                    ->dehydrated(false),
                                TextEntry::make('start_date')
                                    ->label('Perkiraan Mulai Project')
                                    ->date()
                                    ->placeholder('-')
                                    ->dehydrated(false),
                                TextEntry::make('due_date')
                                    ->label('Tenggat Selesai')
                                    ->date()
                                    ->placeholder('-')
                                    ->dehydrated(false),
                                TextEntry::make('quote_notes')
                                    ->label('Catatan dari Owner')
                                    ->placeholder('Tidak ada catatan khusus dari owner.')
                                    ->columnSpanFull()
                                    ->dehydrated(false),
                            ]),
                        Textarea::make('notes')
                            ->label('Catatan Tambahan / Instruksi Khusus')
                            ->placeholder('Tuliskan catatan khusus atau instruksi terkait persetujuan pesanan (opsional)...')
                            ->rows(3),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(OrderQuotationService::class)->approveQuote(
                            $record,
                            auth()->user(),
                            notes: $data['notes'] ?? null,
                        );

                        Notification::make()
                            ->title('Penawaran Berhasil Disetujui')
                            ->body('Pesanan telah disetujui dan siap diproses lebih lanjut.')
                            ->success()
                            ->send();
                    }),
                Action::make('rejectQuote')
                    ->label('Tolak Penawaran')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Order $record): bool => auth()->user()?->can('rejectQuote', $record) ?? false)
                    ->modalHeading('Tolak Penawaran Pesanan')
                    ->modalDescription('Pesanan akan dibatalkan permanen dan tidak dapat dilanjutkan kembali.')
                    ->schema([
                        Textarea::make('cancellation_reason')
                            ->label('Alasan Penolakan')
                            ->placeholder('Tuliskan alasan mengapa penawaran ini ditolak...')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Order $record, array $data): void {
                        app(OrderQuotationService::class)->rejectQuote(
                            $record,
                            auth()->user(),
                            $data['cancellation_reason'],
                        );

                        Notification::make()
                            ->title('Penawaran Ditolak')
                            ->body('Pesanan telah dibatalkan dan ditutup.')
                            ->danger()
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
