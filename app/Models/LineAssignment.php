<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penugasan penjahit ke line produksi (FR-009).
 * Penugasan ditutup dengan mengisi unassigned_at, bukan dihapus, agar riwayat
 * alokasi tetap dapat diaudit.
 */
class LineAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_line_id',
        'user_id',
        'station_number',
        'assigned_at',
        'unassigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->unassigned_at === null;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('unassigned_at');
    }
}
