<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToClient;
use App\Enums\RateType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rate card ongkos potong/jahit per artikel, komponen, dan klien (FR-010).
 *
 * Berlaku efektif dengan rentang tanggal — tarif lama tidak ditimpa, sehingga
 * payroll periode lalu tetap dapat dihitung ulang dengan tarif yang benar
 * saat itu dan riwayat perubahan tarif tetap dapat diaudit.
 */
class RateCard extends Model
{
    use Auditable;
    use BelongsToClient;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'article_id',
        'component_id',
        'rate_type',
        'rate',
        'effective_from',
        'effective_to',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'rate_type' => RateType::class,
            'rate' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
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

    public function isCurrentlyEffective(): bool
    {
        $today = now()->startOfDay();

        return $this->effective_from->lte($today)
            && ($this->effective_to === null || $this->effective_to->gte($today));
    }
}
