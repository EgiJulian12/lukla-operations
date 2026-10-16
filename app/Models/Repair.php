<?php

namespace App\Models;

use App\Enums\RepairStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model Repair untuk mengelola unit yang perlu perbaikan (FR-014, BR 4.6).
 */
class Repair extends Model
{
    use HasFactory;

    protected $fillable = [
        'qc_record_id',
        'assigned_repairer_id',
        'status',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => RepairStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function qcRecord(): BelongsTo
    {
        return $this->belongsTo(QcRecord::class);
    }

    public function assignedRepairer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_repairer_id');
    }

    public function client()
    {
        return $this->qcRecord?->client;
    }

    public function order()
    {
        return $this->qcRecord?->order;
    }

    public function assembly()
    {
        return $this->qcRecord?->assembly;
    }

    public function defectCategory()
    {
        return $this->qcRecord?->defectCategory;
    }

    public function scopeReadyForQc($query)
    {
        return $query->where('status', RepairStatus::COMPLETED->value);
    }
}
