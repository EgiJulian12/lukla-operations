<?php

namespace App\Console\Commands;

use App\Services\Payroll\PayrollService;
use Illuminate\Console\Command;

/**
 * Menutup periode payroll yang sudah berakhir secara otomatis (Req 8.1).
 *
 * Dijadwalkan tiap Sabtu 00:00 WIB oleh scheduler di routes/console.php.
 * Mencari semua periode dengan period_end < hari ini dan status OPEN/CALCULATED,
 * lalu menjalankan calculate + approve untuk masing-masing.
 */
class PayrollCloseWeeklyCommand extends Command
{
    protected $signature = 'payroll:close-weekly';

    protected $description = 'Tutup periode payroll yang sudah berakhir (Sabtu 00:00 WIB)';

    public function handle(PayrollService $payrollService): int
    {
        $this->info('Memulai penutupan periode payroll...');

        $results = $payrollService->closeExpiredPeriods();

        if (empty($results)) {
            $this->info('Tidak ada periode yang perlu ditutup.');

            return self::SUCCESS;
        }

        $this->table(
            ['Period ID', 'Periode', 'Status', 'Jumlah Penjahit'],
            array_map(
                fn (array $r): array => [
                    $r['period_id'],
                    $r['label'],
                    $r['status'],
                    $r['entries'],
                ],
                $results,
            ),
        );

        $errors = array_filter($results, fn (array $r): bool => str_starts_with($r['status'], 'ERROR'));

        if (! empty($errors)) {
            $this->warn(count($errors).' periode gagal ditutup. Periksa log untuk detail.');

            return self::FAILURE;
        }

        $this->info(count($results).' periode berhasil ditutup.');

        return self::SUCCESS;
    }
}
