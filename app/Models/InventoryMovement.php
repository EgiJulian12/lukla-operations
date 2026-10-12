<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToClient;
use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pergerakan stok (FR-006, FR-007).
 *
 * client_id dan order_id WAJIB sama dengan item stok yang dipindahkan.
 * FK komposit di database menolak ketidakcocokan, sehingga percampuran bahan
 * antar klien mustahil terjadi meski validasi aplikasi dilewati.
 */
class InventoryMovement extends Model
{
    use Auditable;
    use BelongsToClient;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'order_id',
        'inventory_item_id',
        'movement_type',
        'quantity',
        'reference',
        'notes',
        'created_by',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'movement_type' => MovementType::class,
            'quantity' => 'decimal:2',
            'recorded_at' => 'datetime',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Pergerakan yang mengurangi stok perlu divalidasi kecocokan
     * klien/pesanan sebelum dicatat (FR-007).
     */
    public function requiresOwnershipMatch(): bool
    {
        return $this->movement_type->requiresOwnershipMatch();
    }
}
