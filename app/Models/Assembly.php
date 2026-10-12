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
}
