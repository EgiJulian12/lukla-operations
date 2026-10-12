<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToClient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan sisa potongan bahan / scrap (FR-008).
 * Terikat klien dan pesanan untuk pelaporan efisiensi bahan per klien.
 */
class WasteRecord extends Model
{
    use Auditable;
    use BelongsToClient;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'order_id',
        'material_id',
        'quantity',
        'uom',
        'category',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
