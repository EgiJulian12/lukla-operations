<?php

namespace App\Models;

use App\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Perakitan komponen menjadi produk utuh (FR-013).
 *
 * Setelah dirakit, produk berpindah ke status siap QC. Output terverifikasi
 * yang menyusun perakitan ini direkam lewat tabel pivot, sehingga asal-usul
 * setiap unit dapat ditelusuri kembali ke penjahitnya.
 */
class Assembly extends Model
{
    use BelongsToClient;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'order_id',
        'quantity',
        'assembled_by',
        'assembled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'assembled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assembler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assembled_by');
    }

    public function outputEntries(): BelongsToMany
    {
        return $this->belongsToMany(OutputEntry::class);
    }

    public function qcRecords(): HasMany
    {
        return $this->hasMany(QcRecord::class);
    }

    /**
     * Jumlah unit yang sudah diperiksa QC.
     */
    public function inspectedQuantity(): int
    {
        return (int) $this->qcRecords()->sum('quantity');
    }

    /**
     * Sisa unit yang masih menunggu pemeriksaan QC.
     */
    public function remainingQuantity(): int
    {
        return max(0, $this->quantity - $this->inspectedQuantity());
    }

    /**
     * Apakah seluruh kuantitas perakitan telah selesai diperiksa.
     */
    public function isFullyInspected(): bool
    {
        return $this->remainingQuantity() === 0;
    }

    /**
     * Filter hanya perakitan yang masih memiliki unit belum diperiksa QC.
     */
    public function scopePendingQc($query)
    {
        return $query->whereRaw('quantity > (SELECT COALESCE(SUM(quantity), 0) FROM qc_records WHERE qc_records.assembly_id = assemblies.id)');
    }
}
