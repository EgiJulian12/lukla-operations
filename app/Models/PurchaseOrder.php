<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PO bahan — pesanan pembelian ke pemasok (matriks RBAC SRS, Finance).
 *
 * Pemasok disimpan sebagai teks bebas karena BRD mengeluarkan manajemen
 * pemasok dari lingkup; yang wajib adalah kemampuan membuat dan menyetujui PO.
 * Nilai PO diturunkan dari rinciannya, bukan disimpan terpisah, supaya tidak
 * ada dua sumber kebenaran yang bisa berbeda.
 */
class PurchaseOrder extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'po_number',
        'supplier',
        'status',
        'ordered_at',
        'expected_at',
        'approved_by',
        'approved_at',
        'notes',
    ];

    /**
     * Status default untuk model baru yang belum disimpan, agar policy
     * tidak menemui null saat authorization dievaluasi.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'ordered_at' => 'date',
            'expected_at' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * PO yang sudah disetujui terkunci dan tidak dapat disunting lagi (NFR-005).
     *
     * Null-safe: status bisa null pada model yang belum disimpan saat policy dievaluasi.
     */
    public function isLocked(): bool
    {
        return $this->status?->isLocked() ?? false;
    }

    public function totalAmount(): float
    {
        return round((float) $this->items()
            ->get()
            ->sum(fn (PurchaseOrderItem $item): float => $item->lineTotal()), 2);
    }
}
