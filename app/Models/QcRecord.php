<?php

namespace App\Models;

use App\Concerns\BelongsToClient;
use App\Enums\QcResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Hasil inspeksi QC (FR-014).
 *
 * Hanya unit LOLOS yang membentuk pool bonus QC lintas order dan lintas klien
 * (FR-017), sehingga kepemilikan klien/pesanan tetap direkam untuk rekonsiliasi.
 */
class QcRecord extends Model
{
    use BelongsToClient;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'order_id',
        'assembly_id',
        'result',
        'defect_category_id',
        'quantity',
        'inspected_by',
        'inspected_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'result' => QcResult::class,
            'quantity' => 'integer',
            'inspected_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assembly(): BelongsTo
    {
        return $this->belongsTo(Assembly::class);
    }

    public function defectCategory(): BelongsTo
    {
        return $this->belongsTo(DefectCategory::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function repair(): HasOne
    {
        return $this->hasOne(Repair::class);
    }

    /**
     * Hanya unit lolos yang membentuk pool bonus (FR-017).
     */
    public function countsTowardBonusPool(): bool
    {
        return $this->result->countsTowardBonusPool();
    }
}
