<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToClient;
use App\Enums\RateCardStatus;
use App\Enums\RateType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rate card ongkos potong/jahit per artikel, komponen, dan klien (FR-010).
 *
 * Mendukung approval status (pending, approved, rejected). Jika harga ditolak,
 * perubahan dilakukan secara in-place pada record yang sama dan status kembali pending.
 */
class RateCard extends Model
{
    use Auditable;
    use BelongsToClient;
    use HasFactory;

    protected $attributes = [
        'status' => RateCardStatus::PENDING,
    ];

    protected $fillable = [
        'client_id',
        'article_id',
        'component_id',
        'rate_type',
        'rate',
        'status',
        'effective_from',
        'effective_to',
        'created_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'rate_type' => RateType::class,
            'rate' => 'decimal:2',
            'status' => RateCardStatus::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // In-place update reset: jika tarif yang pernah ditolak diubah angkanya,
        // kembalikan statusnya ke PENDING untuk menunggu approval ulang.
        static::updating(function (RateCard $rateCard): void {
            if ($rateCard->isDirty('rate') && $rateCard->status === RateCardStatus::REJECTED) {
                $rateCard->status = RateCardStatus::PENDING;
                $rateCard->approved_by = null;
                $rateCard->approved_at = null;
            }
        });
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Rate card yang berlaku pada tanggal tertentu.
     *
     * @param  Builder<RateCard>  $query
     * @return Builder<RateCard>
     */
    public function scopeEffectiveOn(Builder $query, \DateTimeInterface|string $date): Builder
    {
        return $query
            ->where('effective_from', '<=', $date)
            ->where(function (Builder $q) use ($date): void {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $date);
            });
    }

    /**
     * Scope hanya untuk tarif yang sudah disetujui (Approved).
     *
     * @param  Builder<RateCard>  $query
     * @return Builder<RateCard>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', RateCardStatus::APPROVED->value);
    }

    public function isCurrentlyEffective(): bool
    {
        $today = now()->startOfDay();

        return $this->status === RateCardStatus::APPROVED
            && $this->effective_from->lte($today)
            && ($this->effective_to === null || $this->effective_to->gte($today));
    }

    public function approve(User $approver): bool
    {
        return $this->update([
            'status' => RateCardStatus::APPROVED,
            'approved_by' => $approver->getKey(),
            'approved_at' => now(),
        ]);
    }

    public function reject(?string $reason = null, ?User $rejector = null): bool
    {
        $notes = $reason !== null
            ? trim(($this->notes ? $this->notes."\n" : '').'Catatan penolakan: '.$reason)
            : $this->notes;

        return $this->update([
            'status' => RateCardStatus::REJECTED,
            'approved_by' => $rejector?->getKey(),
            'notes' => $notes,
        ]);
    }
}
