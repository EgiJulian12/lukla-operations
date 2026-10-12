<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pool bonus QC (FR-017).
 *
 * Pool dibentuk dari SELURUH unit yang lolos QC lintas order dan lintas klien,
 * dikalikan rate bonus per pcs. Total pool dibagi RATA kepada 3 penerima:
 * 2 Mandor dan 1 Kepala Operasional.
 *
 * recipient_count disimpan eksplisit agar pembagian tetap dapat diaudit
 * meski kebijakan jumlah penerima berubah di kemudian hari.
 */
class QcBonusPool extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id',
        'total_passed_units',
        'rate_per_unit',
        'total_pool',
        'recipient_count',
        'amount_per_recipient',
    ];

    protected function casts(): array
    {
        return [
            'total_passed_units' => 'integer',
            'rate_per_unit' => 'decimal:2',
            'total_pool' => 'decimal:2',
            'recipient_count' => 'integer',
            'amount_per_recipient' => 'decimal:2',
        ];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(QcBonusDistribution::class);
    }

    /**
     * Menghitung pembagian rata ke seluruh penerima (FR-017).
     *
     * Pembulatan dikerahkan ke penerima terakhir agar total distribusi
     * selalu sama persis dengan total pool — tidak ada rupiah yang hilang.
     *
     * @return array<int, string> jumlah per penerima, dalam urutan penerima
     */
    public function splitEqually(): array
    {
        $count = max(1, (int) $this->recipient_count);
        $pool = (float) $this->total_pool;

        $base = round($pool / $count, 2);
        $amounts = array_fill(0, $count, number_format($base, 2, '.', ''));

        // Selisih pembulatan dibebankan ke penerima terakhir.
        $remainder = round($pool - ($base * $count), 2);
        if ($remainder !== 0.0) {
            $amounts[$count - 1] = number_format($base + $remainder, 2, '.', '');
        }

        return $amounts;
    }
}
