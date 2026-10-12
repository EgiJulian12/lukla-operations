<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToClient;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoice klien (matriks RBAC SRS, Finance: terbitkan invoice).
 *
 * Invoice menempel pada klien dan opsional pada satu order. Setelah terbit,
 * nilai tagihan terkunci dan tidak disunting lagi (NFR-005).
 */
class Invoice extends Model
{
    use Auditable;
    use BelongsToClient;
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'client_id',
        'order_id',
        'status',
        'amount',
        'issued_at',
        'due_at',
        'issued_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'amount' => 'decimal:2',
            'issued_at' => 'date',
            'due_at' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }
}
