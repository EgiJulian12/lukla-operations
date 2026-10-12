<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rate bonus QC per unit (FR-017).
 *
 * Disimpan per periode, sejajar dengan meal_allowance_percent, supaya kebijakan
 * bonus dapat berubah antar periode tanpa perubahan kode sistem. Nullable karena
 * periode boleh dibuka sebelum kebijakannya ditetapkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->decimal('qc_bonus_rate_per_unit', 12, 2)
                ->nullable()
                ->after('meal_allowance_percent');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn('qc_bonus_rate_per_unit');
        });
    }
};
