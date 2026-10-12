<?php

namespace App\Models;

use App\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Time study per komponen (FR-002). Durasi dalam detik.
 */
class TimeStudy extends Model
{
    use BelongsToClient;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'sample_revision_id',
        'component_id',
        'duration_seconds',
        'recorded_by',
        'recorded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function sampleRevision(): BelongsTo
    {
        return $this->belongsTo(SampleRevision::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Durasi dalam format menit:detik untuk ditampilkan di UI.
     */
    public function getFormattedDurationAttribute(): string
    {
        $minutes = intdiv($this->duration_seconds, 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
