<?php

namespace App\Filament\Portal\Resources\Orders\Schemas;

use App\Enums\OutputStatus;
use App\Enums\QcResult;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Rincian satu pesanan milik klien (FR-025).
 *
 * Progres dihitung dari output yang sudah diverifikasi mandor dan dari hasil
 * QC, sehingga klien melihat pekerjaan yang diakui pabrik — bukan laporan
 * mentah. Lokasi rak internal tidak ikut ditampilkan.
 */
class PortalOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Permintaan Design')
                    ->description('Gambar dan deskripsi yang Anda ajukan saat membuat pesanan.')
                    ->headerActions([
                        Action::make('downloadDesigns')
                            ->label('Download Dokumen Desain')
                            ->icon(Heroicon::OutlinedArrowDownTray)
                            ->color('primary')
                            ->url(fn (Order $record): string => route('orders.designs.download', $record))
                            ->openUrlInNewTab()
                            ->visible(fn (Order $record): bool => ! empty($record->design_images)),
                    ])
                    ->schema([
                        TextEntry::make('description')
                            ->label('Deskripsi')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        // Bucket s3 privat, jadi URL polos ditolak 403; biarkan
                        // Filament menerbitkan URL bertanda tangan.
                        ImageEntry::make('design_images')
                            ->label('Gambar Design')
                            ->disk(config('filesystems.default', 'local'))
                            ->placeholder('Belum ada gambar design.')
                            ->columnSpanFull(),
                        TextEntry::make('design_download_hint')
                            ->hiddenLabel()
                            ->visible(fn (Order $record): bool => ! empty($record->design_images))
                            ->state(fn (): string => 'Kendala melihat gambar preview di atas? Unduh berkas desain langsung:')
                            ->hintAction(
                                Action::make('downloadFallback')
                                    ->label('Download Dokumen')
                                    ->icon(Heroicon::OutlinedArrowDownTray)
                                    ->url(fn (Order $record): string => route('orders.designs.download', $record))
                                    ->openUrlInNewTab()
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Penawaran Owner')
                    ->description('Perkiraan biaya dan catatan dari owner. Setujui penawaran untuk melanjutkan ke tahap sample.')
                    ->schema([
                        TextEntry::make('estimated_cost')
                            ->label('Perkiraan Biaya')
                            ->money('IDR')
                            ->placeholder('Belum ditawar'),
                        TextEntry::make('quoted_at')
                            ->label('Dijawab')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('quote_notes')
                            ->label('Catatan Penawaran')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Pesanan')
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('Nomor Pesanan'),
                        TextEntry::make('work_scheme')
                            ->label('Skema Maklon')
                            ->badge(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('quantity')
                            ->label('Target')
                            ->numeric()
                            ->suffix(' pcs')
                            ->placeholder('-'),
                        TextEntry::make('due_date')
                            ->label('Tenggat')
                            ->date()
                            ->placeholder('-'),
                        TextEntry::make('sampleRevision.version')
                            ->label('Revisi Sample Disetujui')
                            ->formatStateUsing(fn (mixed $state): string => $state !== null ? 'v'.$state : '-'),
                        TextEntry::make('notes')
                            ->label('Catatan Instruksi Klien')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
                Section::make('Progres Produksi')
                    ->schema([
                        TextEntry::make('verified_output')
                            ->label('Output Terverifikasi')
                            ->state(fn (Order $record): int => self::verifiedOutput($record))
                            ->numeric()
                            ->suffix(' pcs'),
                        TextEntry::make('progress')
                            ->label('Progres')
                            ->state(fn (Order $record): string => self::progressLabel($record)),
                        TextEntry::make('passed_units')
                            ->label('Lolos QC')
                            ->state(fn (Order $record): int => self::qcUnits($record, QcResult::PASS))
                            ->numeric()
                            ->suffix(' pcs'),
                        TextEntry::make('repair_units')
                            ->label('Repair')
                            ->state(fn (Order $record): int => self::qcUnits($record, QcResult::REPAIR))
                            ->numeric()
                            ->suffix(' pcs'),
                        TextEntry::make('scrap_units')
                            ->label('Scrap / Defect')
                            ->state(fn (Order $record): int => self::qcUnits($record, QcResult::SCRAP))
                            ->numeric()
                            ->suffix(' pcs'),
                    ])
                    ->columns(3),
                Section::make('Bahan Titipan untuk Pesanan Ini')
                    ->schema([
                        RepeatableEntry::make('inventoryItems')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('material.name')
                                    ->label('Bahan'),
                                TextEntry::make('batch')
                                    ->label('Batch')
                                    ->placeholder('-'),
                                TextEntry::make('state')
                                    ->label('Keadaan')
                                    ->badge(),
                                TextEntry::make('quantity')
                                    ->label('Saldo')
                                    ->numeric(),
                                TextEntry::make('uom')
                                    ->label('Satuan'),
                            ])
                            ->columns(5)
                            ->placeholder('Belum ada bahan titipan untuk pesanan ini.'),
                    ]),
            ]);
    }

    private static function verifiedOutput(Order $order): int
    {
        return (int) $order->outputEntries()
            ->where('status', OutputStatus::VERIFIED->value)
            ->sum('quantity');
    }

    private static function qcUnits(Order $order, QcResult $result): int
    {
        return (int) $order->qcRecords()
            ->where('result', $result->value)
            ->sum('quantity');
    }

    private static function progressLabel(Order $order): string
    {
        $target = (int) $order->quantity;

        if ($target < 1) {
            return '-';
        }

        $percent = (int) round(self::verifiedOutput($order) / $target * 100);

        return $percent.'% dari target';
    }
}
