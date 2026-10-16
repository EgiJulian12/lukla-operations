<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToClient;
use App\Enums\DispatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pelacakan pengantaran bahan dari gudang ke bagian produksi / cutting (FR-012, Track D).
 */
class MaterialDispatch extends Model
{
    use Auditable;
    use BelongsToClient;
    use HasFactory;

    protected $fillable = [
        'dispatch_number',
        'client_id',
        'order_id',
        'inventory_item_id',
        'material_id',
        'quantity',
        'uom',
        'status',
        'rejection_reason',
        'notes',
        'requested_by',
        'approved_by',
        'dispatched_by',
        'received_by',
        'requested_at',
        'approved_at',
        'dispatched_at',
        'received_at',
        'cutting_started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DispatchStatus::class,
            'quantity' => 'decimal:2',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'cutting_started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
