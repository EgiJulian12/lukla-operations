<?php

namespace App\Models;

use App\Enums\OutputStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Upah borongan per penjahit per periode (FR-015).
 *
 * Tidak ada batas jumlah penjahit — penjahit yang bergabung di tengah minggu
 * tetap mendapat barisnya sendiri (NFR-001).
 */
class PayrollEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id',
        'user_id',
        'total_pieces',
        'sewing_amount',
        'meal_allowance_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'total_pieces' => 'integer',
            'sewing_amount' => 'decimal:2',
            'meal_allowance_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Catatan output terverifikasi milik penjahit ini pada rentang periode payroll.
     *
     * @return Collection<int, OutputEntry>
     */
    public function getWorkBreakdown(): Collection
    {
        $period = $this->payrollPeriod;

        if (! $period || ! $period->period_start || ! $period->period_end) {
            return collect();
        }

        return OutputEntry::query()
            ->withoutGlobalScope('client')
            ->where('user_id', $this->user_id)
            ->where('status', OutputStatus::VERIFIED->value)
            ->whereBetween('interval_start', [
                $period->period_start->copy()->startOfDay(),
                $period->period_end->copy()->endOfDay(),
            ])
            ->with(['client', 'order.article', 'component'])
            ->get();
    }

    /**
     * Rincian proyek, klien, dan komponen yang dikerjakan, dikelompokkan per kombinasi order dan komponen.
     *
     * @return array<int, array{client_name: string, order_number: string, article_name: string, component_name: string, total_pieces: int}>
     */
    public function getGroupedWorkBreakdown(): array
    {
        $outputs = $this->getWorkBreakdown();

        if ($outputs->isEmpty()) {
            return [];
        }

        return $outputs
            ->groupBy(fn (OutputEntry $entry): string => sprintf('%s_%s', $entry->order_id ?? 0, $entry->component_id ?? 0))
            ->map(function (Collection $group): array {
                /** @var OutputEntry $first */
                $first = $group->first();

                return [
                    'client_name' => $first->client?->name ?? ($first->order?->client?->name ?? '-'),
                    'order_number' => $first->order?->order_number ?? '-',
                    'article_name' => $first->order?->article?->name ?? '-',
                    'component_name' => $first->component?->name ?? '-',
                    'total_pieces' => (int) $group->sum('quantity'),
                ];
            })
            ->values()
            ->all();
    }
}
