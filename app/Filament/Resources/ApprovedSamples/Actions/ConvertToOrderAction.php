<?php

namespace App\Filament\Resources\ApprovedSamples\Actions;

use App\Enums\OrderStatus;
use App\Enums\WorkScheme;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Sample;
use App\Services\Sample\SampleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Aksi "Jadikan Order Produksi" untuk sample yang sudah disetujui.
 *
 * Dipakai bersama oleh halaman detail dan tabel daftar sample disetujui agar isi modal
 * dan perilakunya identik. Skema maklon, jumlah, dan tenggat selesai diambil dari order
 * terakhir sample — bukan diisi ulang oleh pengguna — lalu order baru langsung
 * dipindahkan ke status Siap Produksi.
 */
class ConvertToOrderAction
{
    /**
     * Pemanggil pada halaman detail wajib menyertakan `->record($this->getRecord())`
     * agar closure dapat di-resolve; pada tabel record sudah di-inject per baris.
     */
    public static function make(): Action
    {
        return Action::make('convert')
            ->label('Jadikan Order Produksi')
            ->icon(Heroicon::OutlinedTruck)
            ->color('success')
            ->visible(fn (Sample $record): bool => (bool) auth()->user()?->can('convertToOrder', $record))
            ->form(function (Sample $record): array {
                $latestOrder = $record->orders()->latest()->first();

                return [
                    Section::make('Informasi Sample')
                        ->description('Data sample yang akan dikonversi menjadi order produksi massal.')
                        ->columns(2)
                        ->schema([
                            Placeholder::make('sample_title')
                                ->label('Nama Sample')
                                ->content($record->title ?? '-'),
                            Placeholder::make('sample_code')
                                ->label('Kode Sample')
                                ->content($record->code ?? '-'),
                            Placeholder::make('article_name')
                                ->label('Artikel')
                                ->content($record->article?->name ?? '-'),
                            Placeholder::make('client_name')
                                ->label('Klien')
                                ->content($record->client?->name ?? '-'),
                        ]),

                    Section::make('Detail Order Produksi')
                        ->description('Skema maklon, jumlah, dan tenggat mengikuti order terakhir sample ini.')
                        ->columns(2)
                        ->schema([
                            Placeholder::make('work_scheme_info')
                                ->label('Skema Maklon')
                                ->content($latestOrder?->work_scheme?->getLabel() ?? '-'),
                            Placeholder::make('quantity_info')
                                ->label('Jumlah Produksi Massal')
                                ->content($latestOrder?->quantity
                                    ? number_format($latestOrder->quantity).' pcs'
                                    : '-'),
                            Placeholder::make('due_date_info')
                                ->label('Tenggat Selesai')
                                ->content($latestOrder?->due_date?->translatedFormat('d F Y') ?? '-'),
                            Textarea::make('notes')
                                ->label('Catatan Order')
                                ->columnSpanFull(),
                        ]),
                ];
            })
            ->action(function (Sample $record, array $data): void {
                $latestOrder = $record->orders()->latest()->first();

                $order = app(SampleService::class)->convertToOrder(
                    sample: $record,
                    scheme: $latestOrder?->work_scheme ?? WorkScheme::CMT,
                    quantity: $latestOrder?->quantity ?? 1,
                    dueDate: $latestOrder?->due_date,
                    notes: $data['notes'] ?? null,
                );

                $order->update(['status' => OrderStatus::READY_FOR_PRODUCTION]);
            })
            ->successRedirectUrl(fn (): string => OrderResource::getUrl('index'))
            ->successNotificationTitle('Order produksi massal berhasil dibuat dari sample disetujui');
    }
}
