<?php

namespace App\Models;

use App\Concerns\BelongsToClient;
use App\Enums\OutputStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Output penjahit per interval 30 menit (FR-011, FR-012).
 *
 * Output hanya masuk perhitungan payroll setelah diverifikasi mandor
 * (FR-015). Sekali diverifikasi, catatan terkunci — penjahit tidak dapat
 * mengeditnya lagi (matriks RBAC, Penjahit).
 */
class OutputEntry extends Model
{
    use BelongsToClient;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'order_id',
        'production_line_id',
        'user_id',
        'component_id',
        'quantity',
        'interval_start',
        'interval_end',
        'status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'rejected_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'interval_start' => 'datetime',
            'interval_end' => 'datetime',
            'status' => OutputStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function assemblies(): BelongsToMany
    {
        return $this->belongsToMany(Assembly::class);
    }

    /**
     * Output terkunci setelah diverifikasi — tidak boleh diedit lagi.
     */
    public function isLocked(): bool
    {
        return $this->status->isLocked();
    }

    public function isPayrollEligible(): bool
    {
        return $this->status->isPayrollEligible();
    }
}
