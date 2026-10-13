<?php

namespace App\Services\Payroll;

use App\Enums\QcResult;
use App\Enums\UserRole;
use App\Models\PayrollPeriod;
use App\Models\QcBonusDistribution;
use App\Models\QcBonusPool;
use App\Models\QcRecord;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mengelola pool bonus QC dan distribusinya ke penerima (Req 7.1-7.6, Property 2).
 *
 * Pool dibentuk dari SELURUH unit lolos QC lintas order dan klien pada periode,
 * dikalikan rate bonus per unit, lalu dibagi rata ke 3 penerima:
 * 2 Mandor (PRODUCTION_LEAD) + 1 Kepala Operasional (OWNER_EXECUTIVE).
 *
 * Remainder dari pembulatan dibebankan ke penerima pertama agar total distribusi
 * selalu sama persis dengan total pool (Property 2 — no rupiah gap).
 */
class QcBonusDistributionService
{
    /**
     * Hitung atau perbarui pool bonus QC untuk satu periode payroll.
     *
     * Aman dipanggil ulang selama periode belum terkunci — akan menggantikan
     * pool dan distribusi sebelumnya.
     */
    public function finalizeBonusPool(PayrollPeriod $period): QcBonusPool
    {
        return DB::transaction(function () use ($period): QcBonusPool {
            $passedUnits = (int) QcRecord::query()
                ->withoutGlobalScope('client')
                ->where('result', QcResult::PASS->value)
                ->whereBetween('inspected_at', [
                    $period->period_start->copy()->startOfDay(),
                    $period->period_end->copy()->endOfDay(),
                ])
                ->sum('quantity');

            $ratePerUnit = (float) ($period->qc_bonus_rate_per_unit ?? 0);
            $totalPool = round($passedUnits * $ratePerUnit, 2);

            $pool = QcBonusPool::updateOrCreate(
                ['payroll_period_id' => $period->getKey()],
                [
                    'total_passed_units' => $passedUnits,
                    'rate_per_unit' => $ratePerUnit,
                    'total_pool' => $totalPool,
                    'recipient_count' => 3,
                    'amount_per_recipient' => 3 > 0 ? round($totalPool / 3, 2) : 0,
                ],
            );

            return $pool->refresh();
        });
    }

    /**
     * Distribusikan pool bonus QC ke penerima yang ditentukan (Req 7.2-7.6).
     *
     * Jika jumlah penerima valid bukan 3, kirim alert notifikasi ke Finance
     * dan log error. Distribusi tetap berjalan dengan penerima yang tersedia.
     *
     * Remainder pembulatan dibebankan ke penerima PERTAMA, bukan terakhir,
     * sesuai kebijakan (Req 7.3, Property 2).
     *
     * @param  array<int, int>  $recipientIds  ID user penerima; kosong = pakai default
     * @return array{pool: QcBonusPool, distributions: array<int, array{user_id: int, name: string, amount: float}>, remainder_on_first: float}
     */
    public function distributeBonus(QcBonusPool $pool, array $recipientIds = []): array
    {
        return DB::transaction(function () use ($pool, $recipientIds): array {
            $recipients = $recipientIds !== []
                ? array_values(array_unique(array_map('intval', $recipientIds)))
                : $this->defaultBonusRecipients();

            // Validasi: jumlah penerima harus tepat 3 (Req 7.2, 7.5)
            if (count($recipients) !== 3) {
                $this->alertInvalidRecipientCount(count($recipients), $pool->payrollPeriod);
            }

            $pool->distributions()->delete();

            $amounts = $this->splitWithRemainderOnFirst(
                (float) $pool->total_pool,
                count($recipients),
            );

            $pool->update([
                'recipient_count' => count($recipients),
                'amount_per_recipient' => count($recipients) > 0
                    ? round((float) $pool->total_pool / count($recipients), 2)
                    : 0,
            ]);

            $distributions = [];
            $remainderOnFirst = $amounts['remainder'];

            foreach ($recipients as $index => $userId) {
                QcBonusDistribution::create([
                    'qc_bonus_pool_id' => $pool->getKey(),
                    'user_id' => $userId,
                    'amount' => $amounts['amounts'][$index] ?? '0.00',
                ]);

                $user = User::find($userId);
                $distributions[] = [
                    'user_id' => $userId,
                    'name' => $user?->name ?? "User #{$userId}",
                    'amount' => (float) ($amounts['amounts'][$index] ?? 0),
                ];
            }

            return [
                'pool' => $pool->refresh(),
                'distributions' => $distributions,
                'remainder_on_first' => $remainderOnFirst,
            ];
        });
    }

    /**
     * Penerima default: 2 mandor (PRODUCTION_LEAD) + 1 kepala operasional (OWNER_EXECUTIVE).
     *
     * @return array<int, int>
     */
    public function defaultBonusRecipients(): array
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

    /**
     * Bagi pool secara rata, remainder dibebankan ke penerima pertama (Req 7.3).
     *
     * @return array{amounts: array<int, string>, remainder: float}
     */
    private function splitWithRemainderOnFirst(float $totalPool, int $count): array
    {
        if ($count <= 0) {
            return ['amounts' => [], 'remainder' => 0.0];
        }

        $base = round($totalPool / $count, 2);
        $amounts = array_fill(0, $count, number_format($base, 2, '.', ''));

        $assigned = round($base * $count, 2);
        $remainder = round($totalPool - $assigned, 2);

        if ($remainder !== 0.0) {
            $firstAmount = round($base + $remainder, 2);
            $amounts[0] = number_format($firstAmount, 2, '.', '');
        }

        return ['amounts' => $amounts, 'remainder' => $remainder];
    }

    /**
     * Kirim alert notifikasi ke semua user Finance jika jumlah penerima bukan 3 (Req 7.5).
     */
    private function alertInvalidRecipientCount(int $actualCount, ?PayrollPeriod $period): void
    {
        $periodLabel = $period?->getLabelAttribute() ?? 'periode tidak diketahui';

        Log::warning('QC bonus pool: jumlah penerima tidak valid', [
            'expected' => 3,
            'actual' => $actualCount,
            'period' => $periodLabel,
        ]);

        // Kirim notifikasi database ke semua user Finance & Owner
        $financeUsers = User::query()
            ->role([UserRole::FINANCE_PAYROLL->value, UserRole::OWNER_EXECUTIVE->value])
            ->get();

        foreach ($financeUsers as $user) {
            Notification::make()
                ->title('Peringatan: Penerima Bonus QC Tidak Sesuai')
                ->body(sprintf(
                    'Periode %s: ekspektasi 3 penerima, ditemukan %d. Distribusi tetap berjalan dengan penerima yang tersedia.',
                    $periodLabel,
                    $actualCount,
                ))
                ->warning()
                ->sendToDatabase($user);
        }
    }
}
