<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Biaya overhead operasional pabrik bulanan (FR-018):
 * listrik, air, internet, dan biaya operasional lain yang disetujui.
 */
class OverheadRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_month',
        'category',
        'amount',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
