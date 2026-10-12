<?php

namespace App\Filament\Portal\Widgets;

use App\Enums\OrderStatus;
use App\Models\InventoryItem;
use App\Models\Order;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan pesanan dan stok titipan klien (FR-025).
 *
 * Seluruh angka sudah terpotong ke klien yang login oleh global scope, tanpa
 * kode penyaringan tambahan di widget ini.
 */
class PortalStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * Progres pesanan klien berubah mengikuti input lantai produksi, jadi
     * ringkasan ini menyegarkan diri sendiri tanpa perlu muat ulang manual.
     */
    protected ?string $pollingInterval = '30s';

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        return [
            Stat::make('Pesanan Berjalan', Order::query()
                ->whereNotIn('status', [OrderStatus::COMPLETED->value, OrderStatus::CANCELLED->value])
                ->count())
                ->icon(Heroicon::OutlinedRectangleStack),
            Stat::make('Pesanan Selesai', Order::query()
                ->where('status', OrderStatus::COMPLETED->value)
                ->count())
                ->icon(Heroicon::OutlinedCheckBadge),
            Stat::make('Baris Stok Titipan', InventoryItem::query()->count())
                ->icon(Heroicon::OutlinedArchiveBox),
            Stat::make('Total Saldo Bahan', number_format((float) InventoryItem::query()->sum('quantity')))
                ->description('Akumulasi jumlah pada seluruh baris stok')
                ->icon(Heroicon::OutlinedBeaker),
        ];
    }
}
