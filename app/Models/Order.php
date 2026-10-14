<?php

namespace App\Models;

use App\Concerns\BelongsToClient;
use App\Enums\MaterialResponsibility;
use App\Enums\OrderMaterialStatus;
use App\Enums\OrderStatus;
use App\Enums\QcResult;
use App\Enums\WorkScheme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'warehouse_material_status',
        'po_requested_at',
        'client_confirmed_at',
        'cutting_started_at',
        'dispatched_to_production_at',
        'received_by_production_at',
        'due_date',
        'notes',
        'created_by',
        'completed_at',
    ];

    protected $attributes = [
        'warehouse_material_status' => 'kosong',
    ];

    protected function casts(): array
    {
        return [
            'work_scheme' => WorkScheme::class,
            'status' => OrderStatus::class,
            'warehouse_material_status' => OrderMaterialStatus::class,
            'po_requested_at' => 'datetime',
            'client_confirmed_at' => 'datetime',
            'cutting_started_at' => 'datetime',
            'dispatched_to_production_at' => 'datetime',
            'received_by_production_at' => 'datetime',
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

    public function materialRequirements(): HasMany
    {
        return $this->hasMany(OrderMaterialRequirement::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function outputEntries(): HasMany
    {
        return $this->hasMany(OutputEntry::class);
    }

    public function qcRecords(): HasMany
    {
        return $this->hasMany(QcRecord::class);
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
            '%s ? %s (%s pcs)',
            $this->order_number,
            $this->status->getLabel(),
            number_format($this->quantity),
        );
    }

    /**
     * Apakah order memiliki bahan/aksesoris yang ditanggung oleh perusahaan.
     */
    public function hasCompanyMaterials(): bool
    {
        return $this->work_scheme === WorkScheme::FULL_PACKAGE
            || $this->work_scheme === WorkScheme::SEMI_CMT
            || $this->materialRequirements()->whereIn('responsibility', [
                MaterialResponsibility::COMPANY,
                MaterialResponsibility::PARTIAL,
            ])->exists();
    }

    /**
     * Apakah order memiliki bahan/aksesoris yang ditanggung oleh klien.
     */
    public function hasClientMaterials(): bool
    {
        return $this->work_scheme === WorkScheme::CMT
            || $this->work_scheme === WorkScheme::SEMI_CMT
            || $this->materialRequirements()->whereIn('responsibility', [
                MaterialResponsibility::CLIENT,
                MaterialResponsibility::PARTIAL,
            ])->exists();
    }

    /**
     * Apakah seluruh kebutuhan bahan & aksesoris telah terpenuhi kuantitasnya.
     */
    public function isAllMaterialsFulfilled(): bool
    {
        $requirements = $this->materialRequirements;

        if ($requirements->isEmpty()) {
            return false;
        }

        return $requirements->every(fn (OrderMaterialRequirement $req): bool => $req->isFulfilled());
    }

    /**
     * Persentase pemenuhan kuantitas material pesanan (0-100%).
     */
    public function materialFulfillmentPercentage(): float
    {
        $requirements = $this->materialRequirements;
        if ($requirements->isEmpty()) {
            return 0.0;
        }

        $totalRequired = (float) $requirements->sum('required_quantity');
        if ($totalRequired <= 0) {
            return 100.0;
        }

        $totalReceived = (float) $requirements->sum('received_quantity');

        return min(100.0, round(($totalReceived / $totalRequired) * 100, 1));
    }
}
