<?php

namespace App\Filament\Resources\QcRecords\Tables;

use App\Enums\QcResult;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Riwayat inspeksi QC lintas klien.
 *
 * Tanpa aksi ubah maupun hapus: keputusan QC bersifat final (NFR-005).
 * Data log tidak langsung dimuat semua secara default demi performa dan efisiensi memori;
 * pengguna wajib memfilter (per order/klien/tanggal) atau memilih opsi tampilkan semua.
 */
class QcRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('inspected_at')
                    ->label('Diperiksa')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('client.name')
                    ->label('Klien')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.order_number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('result')
                    ->label('Hasil')
                    ->badge(),
                TextColumn::make('defectCategory.name')
                    ->label('Kategori Cacat')
                    ->placeholder('-'),
                TextColumn::make('quantity')
                    ->label('Unit')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('inspector.name')
                    ->label('Inspector')
                    ->placeholder('-'),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->placeholder('-')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('result')
                    ->label('Hasil QC')
                    ->options(QcResult::class),
                Filter::make('inspected_date')
                    ->label('Tanggal Pemeriksaan')
                    ->form([
                        DatePicker::make('date')
                            ->label('Tanggal Pemeriksaan')
                            ->placeholder('Pilih tanggal...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['date'] ?? null,
                            fn (Builder $query, $date): Builder => $query->whereDate('inspected_at', $date),
                        );
                    }),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersResetAction(fn (Action $action) => $action->view('filament.resources.qc-records.components.filter-actions'))
            ->deferFilters(false)
            ->modifyQueryUsing(function (Builder $query, ?HasTable $livewire = null): Builder {
                if (static::hasActiveFilter($livewire)) {
                    return $query;
                }

                // Default kosong: cegah query berat saat pertama kali membuka halaman
                return $query->whereRaw('1 = 0');
            })
            ->emptyStateHeading(fn (?HasTable $livewire = null): string => static::hasActiveFilter($livewire)
                ? 'Tidak Ada Data Riwayat yang Sesuai'
                : 'Pilih Filter untuk Menampilkan Log QC'
            )
            ->emptyStateDescription(fn (?HasTable $livewire = null): string => static::hasActiveFilter($livewire)
                ? 'Tidak ditemukan riwayat inspeksi QC untuk kriteria filter yang Anda pilih.'
                : 'Untuk menjaga performa dan kecepatan sistem, data log tidak dimuat secara otomatis. Silakan pilih nomor order, klien, tanggal, atau centang "Tampilkan Semua".'
            )
            ->emptyStateIcon(fn (?HasTable $livewire = null): Heroicon => static::hasActiveFilter($livewire)
                ? Heroicon::OutlinedMagnifyingGlass
                : Heroicon::OutlinedFunnel
            )
            ->defaultSort('inspected_at', 'desc');
    }

    /**
     * Memeriksa apakah ada filter aktif atau permintaan untuk menampilkan data.
     */
    public static function hasActiveFilter(?HasTable $livewire): bool
    {
        if (! $livewire) {
            return true;
        }

        $filters = $livewire->tableFilters ?? [];

        // Permintaan eksplisit untuk menampilkan semua data (via tombol di samping Reset Filter)
        if (! empty($livewire->showAll) || ! empty($filters['show_all']['isActive'])) {
            return true;
        }

        // Ada pencarian teks global
        if (filled($livewire->tableSearch ?? null)) {
            return true;
        }

        // Ada filter order
        if (filled($filters['order_id']['value'] ?? null)) {
            return true;
        }

        // Ada filter klien
        if (filled($filters['client_id']['value'] ?? null)) {
            return true;
        }

        // Ada filter hasil QC
        if (filled($filters['result']['value'] ?? null)) {
            return true;
        }

        // Ada filter tanggal pemeriksaan per hari
        if (filled($filters['inspected_date']['date'] ?? null)) {
            return true;
        }

        return false;
    }
}
