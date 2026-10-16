<?php

namespace App\Models;

use App\Concerns\BelongsToClient;
use App\Enums\SampleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Revisi sample (v1, v2, dst) — FR-001, BR-005.
 *
 * Revisi bersifat append-only: mengubah spesifikasi berarti membuat revisi
 * baru, bukan menimpa yang lama, sehingga riwayat tetap dapat ditelusuri
 * hingga konversi menjadi order produksi massal.
 */
class SampleRevision extends Model
{
    use BelongsToClient;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'sample_id',
        'parent_revision_id',
        'version',
        'specs',
        'material_usage',
        'result_image_path',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'specs' => 'array',
            'material_usage' => 'array',
            'status' => SampleStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function parentRevision(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_revision_id');
    }

    public function childRevisions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_revision_id');
    }

    public function timeStudies(): HasMany
    {
        return $this->hasMany(TimeStudy::class);
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
     * Label tampilan yang dipakai di UI — "v1", "v2", dst.
     */
    public function getVersionLabelAttribute(): string
    {
        return 'v'.$this->version;
    }

    public function isApproved(): bool
    {
        return $this->status === SampleStatus::APPROVED;
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function canBeRevised(): bool
    {
        return $this->status->canBeRevised();
    }

    /**
     * Total durasi time study komponen pada revisi ini, dalam detik.
     */
    public function totalTimeStudySeconds(): int
    {
        return (int) $this->timeStudies()->sum('duration_seconds');
    }
}
