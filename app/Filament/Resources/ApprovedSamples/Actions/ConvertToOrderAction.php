<?php

namespace App\Filament\Resources\ApprovedSamples\Actions;

use App\Enums\OrderStatus;
use App\Enums\WorkScheme;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Sample;
use App\Services\Inventory\OrderMaterialFlowService;
use App\Services\Sample\SampleService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Aksi "Jadikan Order Produksi" untuk sample yang sudah disetujui (FR-004, FR-4.1).
 *
 * Dipakai bersama oleh halaman detail dan tabel daftar sample disetujui agar isi modal
 * dan perilakunya identik. Pengguna dapat mengonfirmasi pilihan skema maklon, jumlah produksi,
 * dan tenggat selesai, lalu order baru langsung dipindahkan ke status Siap Produksi.
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
            ->label('Jadikan Daftar Pesanan')
            ->icon(Heroicon::OutlinedTruck)
            ->color('success')
            ->visible(fn (Sample $record): bool => (bool) auth()->user()?->can('convertToOrder', $record))
            ->form(function (Sample $record): array {
                $latestOrder = self::latestOrder($record);

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
                            Placeholder::make('approved_version')
                                ->label('Versi Disetujui')
                                ->content($record->approvedRevision?->version_label ?? '-'),
                        ]),

                    Section::make('Detail Order Produksi')
                        ->description('Tentukan skema maklon, kuantitas massal, dan tenggat penyelesaian.')
                        ->columns(2)
                        ->schema([
                            Select::make('work_scheme')
                                ->label('Skema Maklon')
                                ->options(WorkScheme::class)
                                ->default($latestOrder?->work_scheme?->value ?? WorkScheme::CMT->value)
                                ->required(),
                            TextInput::make('quantity')
                                ->label('Jumlah Produksi Massal (pcs)')
                                ->numeric()
                                ->minValue(1)
                                ->default($latestOrder?->quantity ?? 100)
                                ->required(),
                            DatePicker::make('due_date')
                                ->label('Tenggat Selesai')
                                ->default($latestOrder?->due_date),
                            Textarea::make('notes')
                                ->label('Catatan Order')
                                ->columnSpanFull(),
                        ]),
                ];
            })
            ->action(function (Sample $record, array $data): void {
                $rawScheme = $data['work_scheme'] ?? null;
                $scheme = $rawScheme instanceof WorkScheme
                    ? $rawScheme
                    : (is_string($rawScheme) || is_int($rawScheme) ? WorkScheme::tryFrom($rawScheme) : null) ?? WorkScheme::CMT;
                $quantity = (int) $data['quantity'];
                $existingOrder = $record->primaryOrder();

                if ($existingOrder !== null) {
                    $existingOrder->update([
                        'sample_id' => $record->getKey(),
                        'sample_revision_id' => $record->approved_revision_id,
                        'work_scheme' => $scheme,
                        'quantity' => $quantity,
                        'due_date' => $data['due_date'] ?? $existingOrder->due_date,
                        'notes' => $data['notes'] ?? $existingOrder->notes,
                        'status' => OrderStatus::READY_FOR_PRODUCTION,
                    ]);

                    app(OrderMaterialFlowService::class)->syncRequirementsForOrder($existingOrder);
                } else {
                    $order = app(SampleService::class)->convertToOrder(
                        sample: $record,
                        scheme: $scheme,
                        quantity: $quantity,
                        dueDate: $data['due_date'] ?? null,
                        notes: $data['notes'] ?? null,
                    );

                    $order->update(['status' => OrderStatus::READY_FOR_PRODUCTION]);
                }
            })
            ->successRedirectUrl(fn (): string => OrderResource::getUrl('index'))
            ->successNotificationTitle('Order produksi massal siap dikerjakan di lantai produksi');
    }

    /**
     * Order terakhir sample. Diurutkan lewat id, bukan created_at, karena dua
     * order dapat lahir pada detik yang sama ketika sample dikonversi berulang.
     */
    private static function latestOrder(Sample $sample): ?Order
    {
        return $sample->primaryOrder();
    }
}
