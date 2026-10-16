<?php

namespace App\Services\Payroll;

use App\Enums\OutputStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\RateType;
use App\Enums\UserRole;
use App\Exceptions\InvalidPayrollState;
use App\Models\Assembly;
use App\Models\OutputEntry;
use App\Models\OverheadAllocation;
use App\Models\OverheadRecord;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mesin payroll borongan mingguan (FR-015 sampai FR-018).
 *
 * Empat komponen biaya dihitung dari satu periode:
 *   - borongan: akumulasi output TERVERIFIKASI dikali tarif jahit per komponen;
 *   - uang makan: persentase periode dikali total dana ongkos jahit, dibagi
 *     proporsional terhadap jasa jahit masing-masing penjahit;
 *   - pool bonus QC: seluruh unit lolos QC lintas klien dikali tarif bonus,
 *     lalu dibagi rata kepada para penerima;
 *   - overhead per unit: biaya bulanan dibagi total unit yang dirakit bulan itu.
 *
 * Tidak ada batas jumlah penjahit: penjahit yang bergabung di tengah minggu
 * tetap mendapat barisnya sendiri (BR-006, NFR-001).
 */
class PayrollService
{
    public function __construct(private readonly RateCardResolver $rates) {}

    /**
     * Menghitung ulang seluruh periode: borongan, uang makan, dan pool bonus QC
     * (FR-015, FR-016, FR-017).
     *
     * @param  array<int, int>  $recipientIds  penerima bonus QC; kosong berarti pakai penerima default
     */
    public function calculate(PayrollPeriod $period, array $recipientIds = []): PayrollPeriod
    {
        if ($period->isLocked()) {
            throw InvalidPayrollState::locked($period);
        }

        return DB::transaction(function () use ($period, $recipientIds): PayrollPeriod {
            $operatorTotals = $this->operatorSewingTotals($period);
            $totalSewing = round(array_sum(array_column($operatorTotals, 'sewing')), 2);
            $mealPool = round($totalSewing * (float) ($period->meal_allowance_percent ?? 0) / 100, 2);

            $period->entries()->delete();

            $this->writeEntries($period, $operatorTotals, $totalSewing, $mealPool);
            $this->calculateQcBonusPool($period, $recipientIds);

            $period->update([
                'status' => PayrollPeriodStatus::CALCULATED,
                'total_sewing_cost' => $totalSewing,
                'calculated_by' => auth()->id(),
            ]);

            return $period->refresh();
        });
    }

    public function approve(PayrollPeriod $period): PayrollPeriod
    {
        if ($period->status !== PayrollPeriodStatus::CALCULATED) {
            throw InvalidPayrollState::notCalculated($period);
        }

        $period->update([
            'status' => PayrollPeriodStatus::APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $period->refresh();
    }

    public function markPaid(PayrollPeriod $period): PayrollPeriod
    {
        if ($period->status !== PayrollPeriodStatus::APPROVED) {
            throw InvalidPayrollState::notApproved($period);
        }

        $period->update(['status' => PayrollPeriodStatus::PAID]);

        return $period->refresh();
    }

    /**
     * Tutup periode payroll aktif pada Sabtu 00:00 WIB (Req 8.1).
     *
     * Dijalankan oleh Artisan command `payroll:close-weekly` via scheduler.
     * Mencari periode yang masih OPEN atau CALCULATED dengan period_end = hari ini,
     * memvalidasi waktu penutupan (Sabtu ±1 menit), lalu menjalankan hitung
     * dan persetujuan otomatis jika belum dilakukan.
     *
     * Output entries yang sudah VERIFIED tidak diubah status-nya di DB karena
     * VERIFIED sudah bersifat immutable — locking dijamin oleh status periode.
     */
    public function closePeriod(PayrollPeriod $period, array $recipientIds = []): PayrollPeriod
    {
        if ($period->isLocked()) {
            throw InvalidPayrollState::locked($period);
        }

        return DB::transaction(function () use ($period, $recipientIds): PayrollPeriod {
            // Hitung jika belum dihitung
            if ($period->status === PayrollPeriodStatus::OPEN) {
                $this->calculate($period, $recipientIds);
                $period->refresh();
            }

            // Setujui
            if ($period->status === PayrollPeriodStatus::CALCULATED) {
                $this->approve($period);
                $period->refresh();
            }

            return $period->refresh();
        });
    }

    /**
     * Cari dan tutup semua periode yang period_end-nya sudah lewat dan masih terbuka.
     *
     * Dipanggil oleh Artisan command `payroll:close-weekly`.
     *
     * @return array<int, array{period_id: int, label: string, status: string, entries: int}>
     */
    public function closeExpiredPeriods(): array
    {
        $today = Carbon::now('Asia/Jakarta')->startOfDay();

        $periods = PayrollPeriod::query()
            ->whereNotIn('status', [
                PayrollPeriodStatus::APPROVED->value,
                PayrollPeriodStatus::PAID->value,
            ])
            ->where('period_end', '<', $today->toDateString())
            ->get();

        $results = [];

        foreach ($periods as $period) {
            try {
                $closed = $this->closePeriod($period);
                $results[] = [
                    'period_id' => $closed->id,
                    'label' => $closed->getLabelAttribute(),
                    'status' => $closed->status->getLabel(),
                    'entries' => $closed->entries()->count(),
                ];
            } catch (\Throwable $e) {
                Log::error('Gagal menutup periode payroll', [
                    'period_id' => $period->id,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'period_id' => $period->id,
                    'label' => $period->getLabelAttribute(),
                    'status' => 'ERROR: '.$e->getMessage(),
                    'entries' => 0,
                ];
            }
        }

        return $results;
    }

    /**
     * Menghitung overhead per unit untuk satu bulan (FR-018).
     *
     * Total unit diambil dari perakitan yang selesai pada bulan itu, sehingga
     * pembaginya mencerminkan produk yang benar-benar dihasilkan.
     */
    public function allocateOverhead(DateTimeInterface $month): OverheadAllocation
    {
        $start = Carbon::instance($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $totalOverhead = (float) OverheadRecord::query()
            ->whereBetween('period_month', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        $totalUnits = (int) Assembly::query()
            ->withoutGlobalScope('client')
            ->whereBetween('assembled_at', [$start, $end])
            ->sum('quantity');

        $allocation = OverheadAllocation::updateOrCreate(
            ['period_month' => $start->toDateString()],
            ['total_overhead' => $totalOverhead, 'total_units' => $totalUnits],
        );

        $allocation->update(['overhead_per_unit' => $allocation->calculatePerUnit()]);

        return $allocation->refresh();
    }

    /**
     * Jasa jahit per penjahit dari output TERVERIFIKASI pada rentang periode.
     *
     * @return array<int, array{pieces: int, sewing: float}>
     */
    private function operatorSewingTotals(PayrollPeriod $period): array
    {
        $entries = OutputEntry::query()
            ->withoutGlobalScope('client')
            ->where('status', OutputStatus::VERIFIED->value)
            ->whereBetween('interval_start', [
                $period->period_start->copy()->startOfDay(),
                $period->period_end->copy()->endOfDay(),
            ])
            ->with('component')
            ->get();

        $totals = [];

        foreach ($entries as $entry) {
            $rate = $this->rates->rateFor(
                clientId: (int) $entry->client_id,
                articleId: $entry->component?->article_id !== null ? (int) $entry->component->article_id : null,
                componentId: (int) $entry->component_id,
                type: RateType::SEWING,
                date: $entry->interval_start,
            );

            $totals[$entry->user_id] ??= ['pieces' => 0, 'sewing' => 0.0];
            $totals[$entry->user_id]['pieces'] += (int) $entry->quantity;
            $totals[$entry->user_id]['sewing'] += $rate * (int) $entry->quantity;
        }

        return $totals;
    }

    /**
     * @param  array<int, array{pieces: int, sewing: float}>  $operatorTotals
     */
    private function writeEntries(PayrollPeriod $period, array $operatorTotals, float $totalSewing, float $mealPool): void
    {
        $userIds = array_keys($operatorTotals);
        $mealAmounts = $this->spreadProportionally($mealPool, $operatorTotals);
        $mealAmounts = array_values($mealAmounts);

        foreach ($userIds as $index => $userId) {
            $sewing = round($operatorTotals[$userId]['sewing'], 2);
            $meal = $mealAmounts[$index] ?? 0.0;

            PayrollEntry::create([
                'payroll_period_id' => $period->getKey(),
                'user_id' => $userId,
                'total_pieces' => $operatorTotals[$userId]['pieces'],
                'sewing_amount' => $sewing,
                'meal_allowance_amount' => $meal,
                'total_amount' => round($sewing + $meal, 2),
            ]);
        }
    }

    /**
     * Membagi dana uang makan proporsional terhadap jasa jahit tiap penjahit.
     * Sisa pembulatan dibebankan ke penerima terakhir agar totalnya sama persis
     * dengan dana yang dihitung dari persentase periode.
     *
     * @param  array<int, array{pieces: int, sewing: float}>  $operatorTotals
     * @return array<int, float>
     */
    private function spreadProportionally(float $pool, array $operatorTotals): array
    {
        $totalSewing = array_sum(array_column($operatorTotals, 'sewing'));

        if ($totalSewing <= 0.0 || $pool <= 0.0) {
            return array_map(fn (): float => 0.0, $operatorTotals);
        }

        $amounts = [];

        foreach ($operatorTotals as $userId => $agg) {
            $amounts[$userId] = round($pool * ($agg['sewing'] / $totalSewing), 2);
        }

        $assigned = round(array_sum($amounts), 2);
        $lastKey = array_key_last($amounts);

        if ($lastKey !== null) {
            $amounts[$lastKey] = round($amounts[$lastKey] + ($pool - $assigned), 2);
        }

        return $amounts;
    }

    /**
     * Pool bonus QC dari seluruh unit lolos lintas klien pada periode (FR-017).
     * Dipanggil lewat QcBonusDistributionService sebagai satu-satunya jalur.
     *
     * @param  array<int, int>  $recipientIds
     */
    private function calculateQcBonusPool(PayrollPeriod $period, array $recipientIds): void
    {
        $distributionService = app(QcBonusDistributionService::class);
        $pool = $distributionService->finalizeBonusPool($period);
        $distributionService->distributeBonus($pool, $recipientIds);
    }

    /**
     * Penerima default: dua mandor (Kepala Produksi) dan satu Kepala
     * Operasional (Owner), sesuai kebijakan pembagian tiga arah (FR-017).
     *
     * @return array<int, int>
     */
    public static function defaultBonusRecipients(): array
    {
        $supervisors = User::query()
            ->role(UserRole::PRODUCTION_LEAD->value)
            ->orderBy('id')
            ->limit(2)
            ->pluck('id')
            ->all();

        $head = User::query()
            ->role(UserRole::OWNER_EXECUTIVE->value)
            ->orderBy('id')
            ->limit(1)
            ->pluck('id')
            ->all();

        return array_values(array_map('intval', [...$supervisors, ...$head]));
    }
}
