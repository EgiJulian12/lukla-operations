<?php

namespace App\Filament\Resources\OutputReviews\Schemas;

use App\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Ringkasan order pada halaman verifikasi output.
 *
 * Sengaja ringkas: yang perlu diketahui mandor sebelum memutuskan adalah order
 * mana ini, targetnya berapa, dan komponen mana yang belum lolos verifikasi.
 * Rincian outputnya sendiri ada di tabel di bawah halaman ini.
 */
class OutputReviewInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->description('Identitas order yang outputnya sedang diperiksa.')
                    ->components([
                        Grid::make(3)->components([
                            TextEntry::make('order_number')
                                ->label('Nomor Order')
                                ->weight('bold')
                                ->fontFamily('mono'),
                            TextEntry::make('client.name')
                                ->label('Klien'),
                            TextEntry::make('article.name')
                                ->label('Artikel')
                                ->placeholder('-'),
                            TextEntry::make('work_scheme')
                                ->label('Skema Maklon')
                                ->badge(),
                            TextEntry::make('quantity')
                                ->label('Target')
                                ->formatStateUsing(fn (int $state): string => "{$state} pcs"),
                            TextEntry::make('status')
                                ->label('Status Order')
                                ->badge(),
                            TextEntry::make('due_date')
                                ->label('Tenggat')
                                ->date('d/m/Y')
                                ->placeholder('Tanpa tenggat'),
                            TextEntry::make('assembly_readiness')
                                ->label('Kesiapan Perakitan')
                                ->badge()
                                ->state(fn (Order $record): string => $record->isReadyForAssembly()
                                    ? 'Siap dirakit'
                                    : 'Belum siap')
                                ->color(fn (Order $record): string => $record->isReadyForAssembly()
                                    ? 'success'
                                    : 'warning'),
                            TextEntry::make('component_coverage')
                                ->label('Cakupan Komponen')
                                ->state(fn (Order $record): string => self::componentCoverage($record)),
                        ]),
                    ]),
            ]);
    }

    /**
     * Komponen mana saja yang belum punya output terverifikasi — inilah yang
     * menahan tombol perakitan, jadi disebutkan namanya di sini.
     */
    private static function componentCoverage(Order $record): string
    {
        if ($record->components()->isEmpty()) {
            return 'Artikel belum dipecah menjadi komponen';
        }

        $missing = $record->componentsWithoutVerifiedOutput();

        if ($missing->isEmpty()) {
            return 'Seluruh komponen sudah terverifikasi';
        }

        return 'Belum terverifikasi: '.$missing->pluck('name')->implode(', ');
    }
}
