<?php

namespace App\Filament\Portal\Resources\Samples\Schemas;

use App\Models\Sample;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Infolist ringkasan sample untuk portal klien (Langkah 5 — Review Sample).
 *
 * Menampilkan informasi konteks sample, gambar desain acuan, dan status
 * agar klien maklon dapat mengevaluasi sebelum ACC / Tolak di tabel revisi.
 */
class PortalSampleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Sample')
                    ->description('Ringkasan identitas dan status sample yang sedang Anda evaluasi.')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')
                            ->label('Kode Sample'),
                        TextEntry::make('title')
                            ->label('Judul Sample'),
                        TextEntry::make('article.name')
                            ->label('Artikel')
                            ->placeholder('-'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('revisions_count')
                            ->label('Jumlah Revisi')
                            ->state(fn (Sample $record): int => $record->revisions()->count()),
                        TextEntry::make('created_at')
                            ->label('Tanggal Dibuat')
                            ->dateTime('d M Y'),
                    ]),

                Section::make('Desain & Acuan Permintaan')
                    ->description('Gambar dan deskripsi pesanan yang menjadi acuan pembuatan sample.')
                    ->headerActions([
                        Action::make('downloadDesigns')
                            ->label('Download Berkas Desain')
                            ->icon(Heroicon::OutlinedArrowDownTray)
                            ->color('primary')
                            ->url(fn (Sample $record): ?string => route('samples.designs.download', $record))
                            ->openUrlInNewTab()
                            ->visible(fn (Sample $record): bool => ! empty($record->designImages())),
                    ])
                    ->schema([
                        TextEntry::make('design_description')
                            ->label('Deskripsi Permintaan')
                            ->state(fn (Sample $record): string => $record->designDescription() ?? 'Tidak ada deskripsi khusus.')
                            ->columnSpanFull(),
                        ImageEntry::make('design_images_display')
                            ->label('Gambar Acuan Desain')
                            ->state(fn (Sample $record): array => $record->designImages())
                            ->disk(config('filesystems.default', 'local'))
                            ->placeholder('Belum ada gambar desain.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Spesifikasi & Estimasi Pemakaian Bahan Baku')
                    ->description('Spesifikasi fisik produk dan estimasi pemakaian bahan baku & aksesoris (per 1 pcs) pada revisi terkini.')
                    ->columns(2)
                    ->schema([
                        KeyValueEntry::make('current_specs')
                            ->label('Spesifikasi Fisik Produk')
                            ->keyLabel('Atribut')
                            ->valueLabel('Nilai')
                            ->state(function (Sample $record): array {
                                $revision = $record->approvedRevision ?? $record->currentRevision;
                                $rawSpecs = $revision?->specs ?? [];
                                $internalKeys = ['design_images', 'description', 'order_number'];
                                $cleanSpecs = [];

                                foreach ($rawSpecs as $k => $v) {
                                    if (! in_array($k, $internalKeys, true) && is_scalar($v)) {
                                        $cleanSpecs[$k] = (string) $v;
                                    }
                                }

                                return $cleanSpecs;
                            })
                            ->placeholder('Belum ada spesifikasi fisik yang diinput.'),
                        KeyValueEntry::make('current_material_usage')
                            ->label('Estimasi Pemakaian Bahan Baku & Aksesoris (Per 1 Pcs)')
                            ->keyLabel('Nama Bahan / Aksesoris')
                            ->valueLabel('Konsumsi / Pcs')
                            ->state(function (Sample $record): array {
                                $revision = $record->approvedRevision ?? $record->currentRevision;

                                return $revision?->material_usage ?? [];
                            })
                            ->placeholder('Belum ada estimasi pemakaian bahan baku yang diinput oleh Tim Sample.'),
                    ])
                    ->collapsible(),

                Section::make('Catatan')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Catatan Sample')
                            ->placeholder('Tidak ada catatan.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
