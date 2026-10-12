<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rincian pembagian pool bonus QC ke masing-masing penerima (FR-017).
 */
class QcBonusDistribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'qc_bonus_pool_id',
        'user_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function qcBonusPool(): BelongsTo
    {
        return $this->belongsTo(QcBonusPool::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
