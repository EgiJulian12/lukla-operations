<?php

namespace App\Filament\Portal\Resources\Orders\Pages;

use App\Filament\Portal\Resources\Orders\PortalOrderResource;
use App\Models\Order;
use App\Services\Order\OrderQuotationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

class ViewPortalOrder extends ViewRecord
{
    protected static string $resource = PortalOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Kembali')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(fn (): string => PortalOrderResource::getUrl(panel: 'portal', tenant: auth()->user()?->client)),

            Action::make('approveQuote')
                ->label('Setuju')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (Order $record): bool => auth()->user()?->can('approveQuote', $record) ?? false)
                ->modalHeading('Konfirmasi Persetujuan Penawaran')
                ->modalDescription('Tinjau rincian penawaran biaya dan jadwal dari owner sebelum menyetujui.')
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
        ];
    }
}
