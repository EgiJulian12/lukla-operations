<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Enums\QcResult;
use App\Models\LineAssignment;
use App\Models\Order;
use App\Models\OutputEntry;
use App\Models\ProductionLine;
use App\Models\QcRecord;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ringkasan operasional pabrik (SRS view #1, FR-019).
 *
 * Seluruh angka dihitung langsung dari data operasional, tanpa nilai contoh,
 * sehingga dashboard tidak pernah menampilkan metrik yang tidak nyata.
 */
class OperationalStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $weekStart = now()->startOfWeek();

        $inspected = (int) QcRecord::query()
            ->where('inspected_at', '>=', $weekStart)
            ->sum('quantity');

        $passed = (int) QcRecord::query()
            ->where('result', QcResult::PASS->value)
            ->where('inspected_at', '>=', $weekStart)
            ->sum('quantity');

        $verifiedPieces = (int) OutputEntry::query()
            ->where('status', OutputStatus::VERIFIED->value)
            ->where('interval_start', '>=', $weekStart)
            ->sum('quantity');

        return [
            Stat::make('Line Aktif', ProductionLine::query()->where('is_active', true)->count())
                ->description('Line produksi yang sedang beroperasi')
                ->icon(Heroicon::OutlinedQueueList),
            Stat::make('Penjahit Bertugas', LineAssignment::query()->whereNull('unassigned_at')->count())
                ->description('Penugasan yang masih terbuka')
                ->icon(Heroicon::OutlinedUsers),
            Stat::make('Order Dalam Produksi', Order::query()->whereIn('status', [OrderStatus::READY_FOR_PRODUCTION->value, OrderStatus::IN_PRODUCTION->value])->count())
                ->description('Pesanan berstatus siap atau sedang dikerjakan')
                ->icon(Heroicon::OutlinedRectangleStack),
            Stat::make('Output Terverifikasi', number_format($verifiedPieces).' pcs')
                ->description('Akumulasi sejak awal minggu')
                ->icon(Heroicon::OutlinedCheckBadge),
            Stat::make('Tingkat Lolos QC', $inspected > 0 ? round($passed / $inspected * 100).'%' : '-')
                ->description($inspected > 0
                    ? number_format($passed).' dari '.number_format($inspected).' unit minggu ini'
                    : 'Belum ada inspeksi minggu ini')
                ->icon(Heroicon::OutlinedShieldCheck),
        ];
    }
}
