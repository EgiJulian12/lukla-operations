<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membersihkan seluruh data operasional dan transaksional agar pengujian alur
 * end-to-end dapat dijalankan secara bersih dari awal.
 *
 * Data master (Users, Roles & Permissions, Clients, Defect Categories, Production Lines)
 * tetap dipertahankan.
 */
class CleanTransactionalDataCommand extends Command
{
    protected $signature = 'app:clean-transactions {--force : Jalankan pembersihan tanpa konfirmasi interaktif}';

    protected $description = 'Bersihkan seluruh data transaksional untuk pengujian alur end-to-end';

    /**
     * Daftar tabel transaksional yang akan dikosongkan.
     *
     * @var array<int, string>
     */
    public const TRANSACTIONAL_TABLES = [
        'qc_records',
        'repairs',
        'waste_records',
        'qc_bonus_distributions',
        'qc_bonus_pools',
        'assembly_output_entry',
        'assemblies',
        'output_entries',
        'line_assignments',
        'payroll_entries',
        'payroll_periods',
        'overhead_allocations',
        'overhead_records',
        'invoices',
        'purchase_order_items',
        'purchase_orders',
        'material_dispatches',
        'inventory_movements',
        'inventory_items',
        'order_material_requirements',
        'order_component_assignments',
        'orders',
        'time_studies',
        'rate_cards',
        'sample_revisions',
        'samples',
        'components',
        'articles',
        'materials',
        'audit_logs',
        'telegram_link_tokens',
    ];

    public function handle(): int
    {
        if (! $this->option('force')) {
            $confirmed = $this->confirm(
                'PERINGATAN: Perintah ini akan menghapus SEMUA data pesanan, sampel, transaksi, dan inventaris. Lanjutkan?',
                false,
            );

            if (! $confirmed) {
                $this->info('Operasi dibatalkan.');

                return self::SUCCESS;
            }
        }

        $this->info('Memulai pembersihan data transaksional...');

        $existingTables = array_filter(
            self::TRANSACTIONAL_TABLES,
            fn (string $table): bool => Schema::hasTable($table),
        );

        if (empty($existingTables)) {
            $this->warn('Tidak ada tabel transaksional yang ditemukan.');

            return self::SUCCESS;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $tableList = implode(', ', array_map(fn (string $t): string => "\"{$t}\"", $existingTables));
            DB::statement("TRUNCATE TABLE {$tableList} RESTART IDENTITY CASCADE;");
        } else {
            Schema::disableForeignKeyConstraints();
            foreach ($existingTables as $table) {
                DB::table($table)->truncate();
            }
            Schema::enableForeignKeyConstraints();
        }

        Artisan::call('cache:clear');

        $this->info('Data transaksional berhasil dibersihkan.');
        $this->table(
            ['Tabel Dibersihkan'],
            array_map(fn (string $t): array => [$t], $existingTables),
        );

        return self::SUCCESS;
    }
}
