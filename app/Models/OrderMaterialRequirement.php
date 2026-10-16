<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\ComponentMaterialStatus;
use App\Enums\MaterialItemType;
use App\Enums\MaterialResponsibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * Kebutuhan dan kondisi item bahan/komponen/aksesoris per pesanan (Level 2).
 *
 * @property float|string|null $required_quantity
 * @property float|string|null $received_quantity
 */
class OrderMaterialRequirement extends Model
{
    use Auditable;
    use HasFactory;

    protected $fillable = [
        'order_id',
        'material_id',
        'component_id',
        'inventory_item_id',
        'item_name',
        'item_type',
        'responsibility',
        'status',
        'required_quantity',
        'received_quantity',
        'uom',
        'supplier_or_source',
        'tracking_number_or_batch',
        'notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'item_type' => MaterialItemType::class,
            'responsibility' => MaterialResponsibility::class,
            'status' => ComponentMaterialStatus::class,
            'required_quantity' => 'decimal:2',
            'received_quantity' => 'decimal:2',
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

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Apakah kuantitas yang diterima sudah mencukupi target permintaan.
     */
    public function isFulfilled(): bool
    {
        return (float) $this->received_quantity >= (float) $this->required_quantity;
    }

    /**
     * Jumlah kekurangan kuantitas yang belum diterima.
     */
    public function missingQuantity(): float
    {
        return max(0.0, (float) $this->required_quantity - (float) $this->received_quantity);
    }

    /**
     * Seluruh item fisik stok gudang yang telah diterima untuk kebutuhan bahan ini.
     *
     * Satu requirement dapat memiliki lebih dari satu InventoryItem saat barang datang
     * dalam beberapa batch/surat-jalan terpisah. Gunakan method ini — bukan relasi
     * tunggal `inventoryItem` — setiap kali membutuhkan seluruh batch untuk diproses
     * (cutting, dispatch ke produksi, dst).
     *
     * @return Collection<int, InventoryItem>
     */
    public function getInventoryItems(): Collection
    {
        if (! $this->material_id) {
            return $this->inventoryItem ? collect([$this->inventoryItem]) : collect();
        }

        $items = InventoryItem::where('order_id', $this->order_id)
            ->where('material_id', $this->material_id)
            ->get();

        if ($items->isEmpty() && $this->inventoryItem) {
            return collect([$this->inventoryItem]);
        }

        return $items;
    }
}
