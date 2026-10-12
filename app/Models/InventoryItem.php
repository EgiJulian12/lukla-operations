<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Concerns\BelongsToClient;
use App\Enums\InventoryState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item stok gudang (FR-005).
 *
 * SETIAP item wajib terikat client_id dan order_id — tidak ada item yatim.
 * Kombinasi ini ditegakkan di level database oleh unique(['id','client_id','order_id'])
 * yang menjadi sasaran FK komposit dari inventory_movements, sehingga percampuran
 * bahan antar klien/pesanan ditolak PostgreSQL, bukan hanya oleh kode aplikasi.
 */
class InventoryItem extends Model
{
    use Auditable;
    use BelongsToClient;
    use HasFactory;

    protected $fillable = [
        'client_id',
        'order_id',
        'material_id',
        'batch',
        'state',
        'quantity',
        'uom',
        'location',
    ];

    protected function casts(): array
    {
        return [
            'state' => InventoryState::class,
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

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Kunci kepemilikan gabungan — dipakai untuk memverifikasi kecocokan
     * sebelum transaksi dicoba (FR-007, NFR-002).
     *
     * @return array{client_id: int, order_id: int}
     */
    public function ownershipKey(): array
    {
        return [
            'client_id' => (int) $this->client_id,
            'order_id' => (int) $this->order_id,
        ];
    }

    /**
     * Apakah item ini boleh dikeluarkan untuk pesanan tujuan tertentu?
     */
    public function belongsToOrderOf(Client|int $client, Order|int $order): bool
    {
        $clientId = $client instanceof Client ? $client->getKey() : $client;
        $orderId = $order instanceof Order ? $order->getKey() : $order;

        return (int) $this->client_id === (int) $clientId
            && (int) $this->order_id === (int) $orderId;
    }
}
