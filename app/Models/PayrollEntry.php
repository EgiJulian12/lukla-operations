<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
