<?php

namespace App\Models;

use App\Enums\PayrollPeriodStatus;
use App\Enums\QcResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Periode payroll borongan mingguan (FR-015, FR-016).
 *
 * meal_allowance_percent diinput manual per periode sehingga kebijakan uang
 * makan dapat berubah tanpa perubahan kode sistem (FR-016).
 *
 * Payroll bukan data per-klien: periode mencakup seluruh penjahit lintas order
 * dan lintas klien, sehingga model ini tidak di-scope per klien.
 */
class PayrollPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_start',
        'period_end',
        'status',
        'meal_allowance_percent',
        'qc_bonus_rate_per_unit',
        'total_sewing_cost',
        'calculated_by',
        'approved_by',
        'approved_at',
    ];

    /**
     * Status default untuk model baru yang belum disimpan, agar policy
     * tidak menemui null saat authorization dievaluasi (mis. CreateRecord).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => PayrollPeriodStatus::class,
            'meal_allowance_percent' => 'decimal:2',
            'qc_bonus_rate_per_unit' => 'decimal:2',
            'total_sewing_cost' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }

    public function qcBonusPool(): HasOne
    {
        return $this->hasOne(QcBonusPool::class);
    }

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Periode yang sudah disetujui/dibayar terkunci dan tidak dapat dihitung ulang.
     *
     * Null-safe: status bisa null pada model yang belum disimpan saat policy dievaluasi.
     */
    public function isLocked(): bool
    {
        return $this->status?->isLocked() ?? false;
    }

    public function getLabelAttribute(): string
    {
        return $this->period_start->format('d/m/Y').' — '.$this->period_end->format('d/m/Y');
    }

    /**
     * Catatan QC lolos pada rentang periode payroll ini.
     *
     * @return Collection<int, QcRecord>
     */
    public function getQcPassedBreakdown(): Collection
    {
        if (! $this->period_start || ! $this->period_end) {
            return collect();
        }

        return QcRecord::query()
            ->withoutGlobalScope('client')
            ->where('result', QcResult::PASS->value)
            ->whereBetween('inspected_at', [
                $this->period_start->copy()->startOfDay(),
                $this->period_end->copy()->endOfDay(),
            ])
            ->with(['client', 'order.article'])
            ->get();
    }

    /**
     * Rincian proyek, klien, dan produk/artikel yang lolos QC pada periode ini.
     *
     * @return array<int, array{client_name: string, order_number: string, article_name: string, total_passed: int}>
     */
    public function getGroupedQcPassedBreakdown(): array
    {
        $records = $this->getQcPassedBreakdown();

        if ($records->isEmpty()) {
            return [];
        }

        return $records
            ->groupBy('order_id')
            ->map(function (Collection $group): array {
                /** @var QcRecord $first */
                $first = $group->first();

                return [
                    'client_name' => $first->client?->name ?? ($first->order?->client?->name ?? '-'),
                    'order_number' => $first->order?->order_number ?? '-',
                    'article_name' => $first->order?->article?->name ?? '-',
                    'total_passed' => (int) $group->sum('quantity'),
                ];
            })
            ->values()
            ->all();
    }
}
