<?php

namespace App\Models;

use App\Concerns\BelongsToClient;
use App\Enums\OrderStatus;
use App\Enums\RateCardStatus;
use App\Enums\SampleStatus;
use App\Enums\WorkScheme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Sample — prototipe sebelum produksi massal (FR-001).
 *
 * Sample menyimpan revisi berurutan (v1, v2, dst) dan target skema maklon
 * (CMT, Semi-CMT, CMT+Bahan). Hanya revisi yang disetujui yang boleh
 * dikonversi menjadi order produksi massal (FR-003, FR-004).
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
     * Scope hanya untuk sample yang sudah disetujui (Approved).
     *
     * @param  Builder<Sample>  $query
     * @return Builder<Sample>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', SampleStatus::APPROVED->value);
    }

    /**
     * Scope filter sample berdasarkan skema maklon order terkait (CMT, Semi-CMT, Full Package).
     *
     * @param  Builder<Sample>  $query
     * @return Builder<Sample>
     */
    public function scopeForScheme(Builder $query, WorkScheme|string $scheme): Builder
    {
        $value = $scheme instanceof WorkScheme ? $scheme->value : $scheme;

        return $query->whereHas('orders', fn (Builder $q) => $q->where('work_scheme', $value));
    }

    /**
     * Revisi berikutnya untuk sample ini (v1, v2, dst).
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

    /**
     * Memeriksa apakah sample ini telah memiliki order yang berstatus Siap Produksi
     * atau telah masuk ke tahap produksi massal (Dalam Produksi, QC, atau Selesai).
     */
    public function hasReadyForProductionOrder(): bool
    {
        return $this->orders()
            ->whereIn('status', [
                OrderStatus::READY_FOR_PRODUCTION,
                OrderStatus::IN_PRODUCTION,
                OrderStatus::IN_QC,
                OrderStatus::COMPLETED,
            ])
            ->exists()
            || ($this->article?->order !== null && in_array($this->article->order->status, [
                OrderStatus::READY_FOR_PRODUCTION,
                OrderStatus::IN_PRODUCTION,
                OrderStatus::IN_QC,
                OrderStatus::COMPLETED,
            ], true));
    }

    /**
     * Mengambil daftar path berkas gambar desain acuan untuk sample ini.
     *
     * @return array<int, string>
     */
    public function designImages(): array
    {
        return $this->article?->order?->design_images
            ?? $this->currentRevision?->specs['design_images']
            ?? [];
    }

    /**
     * Mengambil deskripsi permintaan acuan desain untuk sample ini.
     */
    public function designDescription(): ?string
    {
        return $this->article?->order?->description
            ?? $this->currentRevision?->specs['description']
            ?? null;
    }

    /**
     * Menghasilkan URL gambar desain untuk ditampilkan di antarmuka (UI).
     *
     * @return array<int, string>
     */
    public function designImageUrls(): array
    {
        if ($this->article?->order) {
            return $this->article->order->designImageUrls();
        }

        $images = $this->designImages();
        if (empty($images)) {
            return [];
        }

        $diskName = config('filesystems.default', 'local');
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);

        return array_map(
            function (string $path) use ($disk): string {
                try {
                    return $disk->temporaryUrl(
                        $path,
                        now()->addMinutes(config('filament.temporary_file_url_expiry_minutes', 30)),
                    );
                } catch (\Throwable) {
                    return $disk->url($path);
                }
            },
            $images,
        );
    }

    /**
     * Mengambil seluruh RateCard aktif terbaru untuk setiap komponen pada artikel sample ini.
     *
     * @return Collection<int, RateCard>
     */
    public function latestComponentRateCards(): Collection
    {
        $components = $this->article?->components ?? collect();
        if ($components->isEmpty()) {
            return collect();
        }

        $componentIds = $components->pluck('id');

        return RateCard::query()
            ->withoutGlobalScope('client')
            ->where('client_id', $this->client_id)
            ->whereIn('component_id', $componentIds)
            ->orderBy('effective_from', 'desc')
            ->get()
            ->unique('component_id');
    }

    /**
     * Evaluasi ringkasan status tarif borongan komponen.
     */
    public function rateCardStatus(): string
    {
        $components = $this->article?->components ?? collect();
        if ($components->isEmpty()) {
            return 'empty';
        }

        $rateCards = $this->latestComponentRateCards();
        if ($rateCards->isEmpty()) {
            return 'empty';
        }

        if ($rateCards->contains('status', RateCardStatus::REJECTED)) {
            return 'rejected';
        }

        if ($rateCards->contains('status', RateCardStatus::PENDING)) {
            return 'pending';
        }

        if ($rateCards->count() < $components->count()) {
            return 'incomplete';
        }

        if ($rateCards->every(fn (RateCard $rc): bool => $rc->status === RateCardStatus::APPROVED)) {
            return 'approved';
        }

        return 'pending';
    }

    public function rateCardStatusLabel(): string
    {
        return match ($this->rateCardStatus()) {
            'empty' => 'Belum Diinput',
            'pending' => 'Menunggu Persetujuan',
            'rejected' => 'Ditolak / Minta Revisi',
            'incomplete' => 'Belum Lengkap',
            'approved' => 'Disetujui',
            default => '-',
        };
    }

    public function rateCardStatusColor(): string
    {
        return match ($this->rateCardStatus()) {
            'empty' => 'gray',
            'pending' => 'warning',
            'rejected' => 'danger',
            'incomplete' => 'info',
            'approved' => 'success',
            default => 'gray',
        };
    }

    /**
     * Total nominal tarif borongan jahit per potong untuk artikel ini.
     */
    public function totalSewingRate(): float
    {
        return (float) $this->latestComponentRateCards()->sum('rate');
    }

    /**
     * Menandakan apakah ada usulan tarif komponen yang siap disetujui Owner.
     */
    public function hasPendingRateCards(): bool
    {
        return $this->latestComponentRateCards()->contains('status', RateCardStatus::PENDING);
    }
}
