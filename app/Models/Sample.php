<?php

namespace App\Models;

use App\Concerns\BelongsToClient;
use App\Enums\SampleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sample — prototipe sebelum produksi massal (FR-001).
 *
 * Sample menyimpan revisi berurutan (v1, v2, dst). Hanya revisi yang disetujui
 * yang boleh dikonversi menjadi order produksi massal (FR-003, FR-004).
 */
class Sample extends Model
{
    use BelongsToClient;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'article_id',
        'code',
        'title',
        'status',
        'current_revision_id',
        'approved_revision_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SampleStatus::class,
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SampleRevision::class)->orderBy('version');
    }

    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(SampleRevision::class, 'current_revision_id');
    }

    public function approvedRevision(): BelongsTo
    {
        return $this->belongsTo(SampleRevision::class, 'approved_revision_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Revisi berikutnya untuk sample ini (v1, v2, dst).
     *
     * Query disusun ulang, bukan lewat relasi revisions(), karena relasi itu
     * membawa ORDER BY version yang ditolak PostgreSQL pada query agregat.
     */
    public function nextRevisionNumber(): int
    {
        return (int) SampleRevision::query()
            ->where('sample_id', $this->getKey())
            ->max('version') + 1;
    }

    /**
     * Hanya sample dengan revisi disetujui yang dapat dikonversi ke order (FR-004).
     */
    public function isReadyForMassProduction(): bool
    {
        return $this->status === SampleStatus::APPROVED
            && $this->approved_revision_id !== null;
    }
}
