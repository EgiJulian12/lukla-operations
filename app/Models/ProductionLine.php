<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Line produksi paralel (FR-009). Kapasitas cair — tidak ada batas slot kaku.
 * Data pabrik, tidak di-scope per klien; line melayani order lintas klien.
 */
class ProductionLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'capacity',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LineAssignment::class);
    }

    public function outputEntries(): HasMany
    {
        return $this->hasMany(OutputEntry::class);
    }

    /**
     * Penjahit yang saat ini bertugas di line ini.
     */
    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereNull('unassigned_at');
    }
}
