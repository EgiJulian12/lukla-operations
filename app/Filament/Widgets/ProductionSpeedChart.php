<?php

namespace App\Filament\Widgets;

use App\Models\OutputEntry;
use App\Services\Production\ProductionService;
use Filament\Widgets\LineChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Kecepatan produksi per interval 30 menit selama delapan jam terakhir
 * (SRS view #1, FR-019).
 *
 * Satu titik mewakili satu slot waktu nyata di lantai produksi. Awal pengerjaan
 * dibulatkan ke slot 30 menitnya saat dihitung — laporan yang diukur penjahit
 * dengan stopwatch punya waktu awal apa adanya, dan tanpa pembulatan ini
 * laporan tersebut tidak akan masuk slot mana pun.
 */
class ProductionSpeedChart extends LineChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string|Htmlable|null
    {
        return 'Kecepatan Produksi per 30 Menit';
    }

    /**
     * @return array{datasets: array<int, array{label: string, data: array<int, int>}>, labels: array<int, string>}
     */
    protected function getData(): array
    {
        $start = now()->subHours(8)->startOfHour();

        $buckets = [];

        for ($slot = 0; $slot < 16; $slot++) {
            $buckets[$start->copy()->addMinutes(30 * $slot)->format('H:i')] = 0;
        }

        $entries = OutputEntry::query()
            ->where('interval_start', '>=', $start)
            ->get(['quantity', 'interval_start']);

        foreach ($entries as $entry) {
            [$slotStart] = app(ProductionService::class)->calculateInterval($entry->interval_start);
            $label = $slotStart->format('H:i');

            if (array_key_exists($label, $buckets)) {
                $buckets[$label] += (int) $entry->quantity;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pcs selesai',
                    'data' => array_values($buckets),
                ],
            ],
            'labels' => array_keys($buckets),
        ];
    }
}
