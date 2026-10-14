<?php

namespace App\Filament\Resources\ApprovedSamples\Schemas;

use App\Models\Sample;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Infolist detail sample yang disetujui untuk Kepala Produksi dan Tim Sample.
 *
 * Menampilkan ringkasan sample, rincian komponen pengerjaan lengkap dengan
 * durasi time study (SMV) dan tarif borongan (rate card), serta spesifikasi
 * teknis dan estimasi konsumsi bahan baku dari revisi yang disetujui.
 */
class ApprovedSampleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Sample & Skema Maklon')
                    ->description('Ringkasan identitas sample, skema maklon pengerjaan, dan metadata persetujuan revisi.')
                    ->components([
                        Grid::make(3)->components([
                            TextEntry::make('client.name')
                                ->label('Klien')
                                ->weight('bold'),
                            TextEntry::make('article.name')
                                ->label('Artikel')
                                ->weight('bold'),
                            TextEntry::make('code')
                                ->label('Kode Sample')
                                ->fontFamily('mono'),
                            TextEntry::make('title')
                                ->label('Judul Sample'),
                            TextEntry::make('orders.work_scheme')
                                ->label('Skema Maklon Order')
                                ->badge()
                                ->placeholder('Belum Ada Order'),
                            TextEntry::make('status')
                                ->label('Status Sample')
                                ->badge(),
                            TextEntry::make('approvedRevision.version_label')
                                ->label('Revisi Disetujui')
                                ->badge()
                                ->color('success')
                                ->default('-'),
                            TextEntry::make('approvedRevision.approver.name')
                                ->label('Disetujui Oleh')
                                ->default('-'),
                            TextEntry::make('approvedRevision.approved_at')
                                ->label('Waktu Persetujuan')
                                ->dateTime()
                                ->default('-'),
                        ]),
                        TextEntry::make('notes')
                            ->label('Catatan Sample')
                            ->placeholder('Tidak ada catatan khusus.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Rincian Komponen, Time Study & Tarif Borongan')
                    ->description('Daftar urutan komponen pengerjaan artikel, durasi standar time study (SMV), dan status tarif borongan (rate card) aktif untuk panduan kepala produksi.')
                    ->components([
                        ViewEntry::make('components_breakdown')
                            ->hiddenLabel()
                            ->view('filament.resources.approved-samples.components-detail', fn (Sample $record): array => ['record' => $record])
                            ->columnSpanFull(),
                    ]),

                Section::make('Spesifikasi Teknis & Konsumsi Bahan')
                    ->description('Parameter ukuran dan estimasi konsumsi bahan baku per potong dari revisi sample yang disetujui.')
                    ->collapsible()
                    ->components([
                        Grid::make(2)->components([
                            KeyValueEntry::make('approvedRevision.specs')
                                ->label('Spesifikasi & Ukuran')
                                ->keyLabel('Parameter')
                                ->valueLabel('Nilai / Deskripsi'),
                            KeyValueEntry::make('approvedRevision.material_usage')
                                ->label('Konsumsi Bahan Baku (Consumption)')
                                ->keyLabel('Bahan Baku / Komponen')
                                ->valueLabel('Konsumsi per Pcs'),
                        ]),
                        TextEntry::make('approvedRevision.notes')
                            ->label('Catatan Revisi Terpilih')
                            ->placeholder('Tidak ada catatan khusus pada revisi ini.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
