<?php

namespace App\Models;

use App\Concerns\BelongsToClient;
use App\Enums\OrderStatus;
use App\Enums\WorkScheme;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Order produksi massal (FR-004).
 *
 * Order membawa revisi sample yang disetujui dan skema maklon yang dipilih,
 * sehingga spesifikasi yang diproduksi massal selalu dapat ditelusuri kembali
 * ke revisi sample asalnya (BR-005).
 */
class Order extends Model
{
    use BelongsToClient;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'client_id',
        'sample_id',
        'sample_revision_id',
        'order_number',
        'work_scheme',
        'quantity',
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
            'due_date' => 'date',
        ];
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

    public function qcRecords(): HasMany
    {
        return $this->hasMany(QcRecord::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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
            '%s — %s (%s pcs)',
            $this->order_number,
            $this->status->getLabel(),
            number_format($this->quantity),
        );
    }
}
