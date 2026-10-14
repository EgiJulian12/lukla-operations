<?php

namespace App\Models;

use App\Concerns\BelongsToClient;
use App\Enums\OrderStatus;
use App\Enums\OutputStatus;
use App\Enums\QcResult;
use App\Enums\WorkScheme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Order — dari permintaan klien sampai produksi massal (FR-004).
 *
 * Order membawa revisi sample yang disetujui dan skema maklon yang dipilih,
 * sehingga spesifikasi yang diproduksi massal selalu dapat ditelusuri kembali
 * ke revisi sample asalnya (BR-005). Order yang dibuat klien berjalan lebih
 * dulu sebagai permintaan: gambar design dan deskripsi, lalu penawaran owner,
 * sebelum menjadi order produksi.
 */
class Order extends Model
{
    use BelongsToClient;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'article_id',
        'sample_id',
        'sample_revision_id',
        'order_number',
        'work_scheme',
        'quantity',
        'description',
        'design_images',
        'estimated_cost',
        'quote_notes',
        'quoted_at',
        'quoted_by',
        'quote_approved_at',
        'sample_requested_at',
        'status',
        'due_date',
        'notes',
        'created_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'work_scheme' => WorkScheme::class,
            'status' => OrderStatus::class,
            'quantity' => 'integer',
            'design_images' => 'array',
            'estimated_cost' => 'decimal:2',
            'quoted_at' => 'datetime',
            'quote_approved_at' => 'datetime',
            'sample_requested_at' => 'datetime',
            'due_date' => 'date',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function quoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quoted_by');
    }

    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
    }

    public function sampleRevision(): BelongsTo
    {
        return $this->belongsTo(SampleRevision::class, 'sample_revision_id');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function outputEntries(): HasMany
    {
        return $this->hasMany(OutputEntry::class);
    }

    /**
     * Penjahit yang benar-benar mengerjakan order ini, diturunkan dari catatan
     * output 30 menit.
     *
     * Bukan penugasan line: `line_assignments` menautkan penjahit ke line, dan
     * satu line melayani order lintas klien, sehingga keanggotaan line tidak
     * menunjukkan siapa mengerjakan order mana. Ditampilkan sebagai informasi,
     * bukan sumber perhitungan — payroll tetap dihitung dari output terverifikasi.
     *
     * @return BelongsToMany<User, $this>
     */
    public function operators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'output_entries', 'order_id', 'user_id')
            ->distinct();
    }

    public function qcRecords(): HasMany
    {
        return $this->hasMany(QcRecord::class);
    }

    /**
     * Rencana kerja order: penjahit pemegang tiap komponen (FR-009).
     *
     * Ditetapkan sekali saat produksi dimulai, sehingga satu komponen selalu
     * punya tepat satu penjahit. Lihat ProductionService::startProduction().
     *
     * @return HasMany<OrderComponentAssignment, $this>
     */
    public function componentAssignments(): HasMany
    {
        return $this->hasMany(OrderComponentAssignment::class);
    }

    /**
     * Seluruh komponen yang harus dijahit untuk order ini, diambil dari
     * artikelnya dan diurutkan sesuai urutan pengerjaan.
     *
     * Order tanpa artikel — atau artikel yang komponennya belum dipecah — tidak
     * punya komponen, sehingga daftarnya kosong.
     *
     * @return Collection<int, Component>
     */
    public function components(): Collection
    {
        return $this->article?->components ?? new Collection;
    }

    /**
     * Komponen yang menjadi tanggung jawab seorang penjahit pada order ini.
     * Kosong bila order belum punya rencana, atau penjahit itu tidak ditugaskan.
     *
     * @return Collection<int, Component>
     */
    public function componentsAssignedTo(User $tailor): Collection
    {
        return $this->componentAssignments()
            ->where('user_id', $tailor->getKey())
            ->with('component')
            ->get()
            ->pluck('component')
            ->filter()
            ->values();
    }

    /**
     * Apakah rencana penjahit sudah ditetapkan untuk order ini.
     *
     * Order yang berjalan sebelum penugasan per komponen ada tidak punya
     * rencana; pemakainya membutuhkan pembeda ini agar tidak menyimpulkan
     * "penjahit tidak ditugaskan apa pun" dari ketiadaan rencana.
     */
    public function hasComponentPlan(): bool
    {
        return $this->componentAssignments()->exists();
    }

    /**
     * Jumlah output order ini yang masih menunggu keputusan mandor (FR-012).
     */
    public function pendingOutputVerificationCount(): int
    {
        return $this->outputEntries()
            ->where('status', OutputStatus::SUBMITTED->value)
            ->count();
    }

    public function hasPendingOutputVerification(): bool
    {
        return $this->pendingOutputVerificationCount() > 0;
    }

    /**
     * Komponen artikel order yang belum punya satu pun output terverifikasi.
     *
     * Inilah yang menahan perakitan: perakitan menyatukan pekerjaan seluruh
     * komponen, sehingga komponen yang belum lolos verifikasi berarti ada bagian
     * produk yang belum jelas siapa mengerjakan dan berapa hasilnya. Output yang
     * ditolak tidak dihitung — komponen itu harus dilaporkan ulang lalu
     * diverifikasi.
     *
     * @return Collection<int, Component>
     */
    public function componentsWithoutVerifiedOutput(): Collection
    {
        $verifiedComponentIds = $this->outputEntries()
            ->where('status', OutputStatus::VERIFIED->value)
            ->pluck('component_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();

        return $this->components()
            ->reject(fn (Component $component): bool => in_array((int) $component->getKey(), $verifiedComponentIds, true))
            ->values();
    }

    /**
     * Apakah order sudah boleh dirakit (FR-012 → FR-013).
     *
     * Pertanyaannya dijawab satu tempat saja — service dan UI sama-sama memakai
     * method ini — supaya tombol yang aktif dan aksi yang diterima tidak pernah
     * berbeda pendapat.
     *
     * Order yang artikelnya belum dipecah menjadi komponen tidak punya komponen
     * untuk ditunggu; untuk order seperti itu cukup ada satu output terverifikasi
     * sebagai bukti pekerjaannya memang berjalan.
     */
    public function isReadyForAssembly(): bool
    {
        if ($this->hasPendingOutputVerification()) {
            return false;
        }

        if ($this->components()->isEmpty()) {
            return $this->outputEntries()
                ->where('status', OutputStatus::VERIFIED->value)
                ->exists();
        }

        return $this->componentsWithoutVerifiedOutput()->isEmpty();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function assemblies(): HasMany
    {
        return $this->hasMany(Assembly::class);
    }

    /**
     * Jumlah unit dari perakitan yang sudah siap diperiksa QC.
     */
    public function qcReadyQuantity(): int
    {
        return (int) $this->assemblies->sum(fn (Assembly $assembly): int => $assembly->remainingQuantity());
    }

    /**
     * Jumlah unit yang telah dinyatakan lolos (PASS).
     */
    public function qcPassedQuantity(): int
    {
        return (int) $this->qcRecords()->where('result', QcResult::PASS->value)->sum('quantity');
    }

    /**
     * Jumlah unit yang saat ini dalam perbaikan (REPAIR).
     */
    public function qcRepairQuantity(): int
    {
        return (int) $this->qcRecords()->where('result', QcResult::REPAIR->value)->sum('quantity');
    }

    /**
     * Jumlah unit yang dinyatakan afkir/ditolak (SCRAP).
     */
    public function qcScrapQuantity(): int
    {
        return (int) $this->qcRecords()->where('result', QcResult::SCRAP->value)->sum('quantity');
    }

    /**
     * Persentase pencapaian lolos QC terhadap target pesanan.
     */
    public function qcProgressPercentage(): float
    {
        if ($this->quantity <= 0) {
            return 0.0;
        }

        return min(100.0, round(($this->qcPassedQuantity() / $this->quantity) * 100, 1));
    }

    /**
     * Apakah terdapat unit yang sedang siap dicek QC.
     */
    public function hasReadyQc(): bool
    {
        return $this->qcReadyQuantity() > 0;
    }

    /**
     * Order yang terkunci (selesai/dibatalkan) tidak boleh diubah lagi.
     * Mendukung aturan matriks RBAC tentang histori transaksi terkunci.
     */
    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    /**
     * Order yang sudah masuk tahap kerja fisik.
     *
     * Permintaan klien yang belum disetujui tidak punya bahan, output, maupun
     * sample, sehingga tidak boleh muncul di dashboard produksi dan antrean QC.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeProductionStage(Builder $query): Builder
    {
        return $query->whereIn('status', [
            OrderStatus::DRAFT->value,
            OrderStatus::CONFIRMED->value,
            OrderStatus::READY_FOR_PRODUCTION->value,
            OrderStatus::IN_PRODUCTION->value,
            OrderStatus::IN_QC->value,
            OrderStatus::COMPLETED->value,
        ]);
    }

    /**
     * Penawaran sudah dikirim owner dan belum diputuskan klien.
     */
    public function awaitingQuotation(): bool
    {
        return $this->status->needsQuotation();
    }

    public function hasApprovedQuote(): bool
    {
        return $this->status->isQuoteApproved();
    }

    /**
     * URL gambar design untuk ditampilkan di UI. Path disimpan relatif
     * terhadap disk s3 sehingga dapat dipindahkan tanpa mengubah data;
     * bucket privat, jadi URL polos ditolak dan harus bertanda tangan.
     *
     * @return array<int, string>
     */
    public function designImageUrls(): array
    {
        return array_map(
            fn (string $path): string => Storage::disk('s3')->temporaryUrl(
                $path,
                now()->addMinutes(config('filament.temporary_file_url_expiry_minutes', 30)),
            ),
            $this->design_images ?? [],
        );
    }

    /**
     * Apakah order ini sudah mempunyai invoice draf atau terbit.
     *
     * Finance perlu tahu order mana yang belum ditagihkan saat membuat invoice
     * baru, sehingga tidak ada double-billing.
     */
    public function hasActiveInvoice(): bool
    {
        return $this->invoices()
            ->whereIn('status', ['draft', 'issued'])
            ->exists();
    }

    /**
     * Label ringkas untuk dropdown invoice: nomor order + status + qty.
     */
    public function invoiceSelectLabel(): string
    {
        return sprintf(
            '%s � %s (%s pcs)',
            $this->order_number,
            $this->status->getLabel(),
            number_format($this->quantity),
        );
    }
}
