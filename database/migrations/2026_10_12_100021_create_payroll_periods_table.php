<?php

use App\Enums\PayrollPeriodStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periode payroll borongan mingguan (FR-015, FR-016).
 *
 * meal_allowance_percent diinput manual per periode sehingga kebijakan uang
 * makan dapat berubah tanpa perubahan kode sistem. Persentase dihitung dari
 * total dana ongkos jahit periode tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default(PayrollPeriodStatus::OPEN->value);
            $table->decimal('meal_allowance_percent', 5, 2)->nullable();
            $table->decimal('total_sewing_cost', 16, 2)->default(0);
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['period_start', 'period_end']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
