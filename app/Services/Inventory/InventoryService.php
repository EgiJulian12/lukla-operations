<?php

namespace App\Services\Inventory;

use App\Enums\InventoryState;
use App\Enums\MovementType;
use App\Exceptions\InsufficientStock;
use App\Exceptions\InventoryOwnershipMismatch;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\Order;
use App\Models\WasteRecord;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya jalur tulis (write path) untuk stok gudang — FR-005 sampai FR-008.
 *
 * Penerimaan, pengeluaran ke cutting, transfer, penyesuaian, dan catatan scrap
 * semuanya melewati service ini. Halaman Filament tidak menulis model stok
 * secara langsung, sehingga aturan anti-kontaminasi silang tidak dapat
 * dilewati dari UI.
 *
 * Tiga lapisan pertahanan yang saling menutup:
 *   1. Validasi di sini — memberi peringatan eksplisit ketidakcocokan pemilik
 *      SEBELUM transaksi dicoba (NFR-002).
 *   2. Scope BelongsToClient — memotong query per klien.
 *   3. FK komposit inventory_movements(id, client_id, order_id) — menolak
 *      ketidakcocokan di level PostgreSQL meski lapisan 1 dilewati (bug,
 *      seeder, atau perintah SQL langsung).
 *
 * Jejak audit ditulis otomatis oleh trait Auditable pada model-model stok
 * (FR-022), jadi service ini tidak perlu mencatatnya sendiri.
 *
 * Konvensi jumlah: kolom `inventory_movements.quantity` menyimpan PERUBAHAN
 * BERTANDA terhadap stok. Dengan begitu berlaku invarian
 * `inventory_items.quantity == Σ inventory_movements.quantity`.
 */
class InventoryService
{
    /**
     * Menerima bahan dari klien dan menandai kepemilikan klien/pesanan (FR-005).
     *
     * Item dibuat dengan jumlah 0; jumlah sebenarnya datang dari pergerakan
     * RECEIPT, sehingga invarian jumlah di atas tetap terjaga.
     */
    public function receive(
        Order $order,
        Material $material,
        float $quantity,
        ?string $batch = null,
        ?string $location = null,
        InventoryState $state = InventoryState::RAW,
        ?string $reference = null,
    ): InventoryItem {
        return DB::transaction(function () use ($order, $material, $quantity, $batch, $location, $state, $reference): InventoryItem {
            $item = InventoryItem::create([
                'client_id' => $order->client_id,
                'order_id' => $order->getKey(),
                'material_id' => $material->getKey(),
                'batch' => $batch,
                'state' => $state,
                'quantity' => 0,
                'uom' => $material->uom,
                'location' => $location,
            ]);

            $this->recordMovement($item, MovementType::RECEIPT, $quantity, reference: $reference);

            return $item->refresh();
        });
    }

    /**
     * Mencatat satu pergerakan stok dan menyesuaikan jumlah item (FR-006, FR-007).
     *
     * Untuk pengeluaran dan transfer, `$destination` wajib diisi dan wajib
     * cocok dengan kepemilikan item; ketidakcocokan dilempar sebagai
     * InventoryOwnershipMismatch sebelum ada baris apa pun yang ditulis.
     *
     * @throws InventoryOwnershipMismatch|InsufficientStock|InvalidArgumentException
     */
    public function recordMovement(
        InventoryItem $item,
        MovementType $type,
        float $quantity,
        ?Order $destination = null,
        ?string $reference = null,
        ?string $notes = null,
    ): InventoryMovement {
        $this->assertQuantityIsSane($type, $quantity);
        $this->assertDestinationMatches($item, $type, $destination);

        return DB::transaction(function () use ($item, $type, $quantity, $reference, $notes): InventoryMovement {
            $delta = $this->signedDelta($type, $quantity);
            $resulting = (float) $item->quantity + $delta;

            if ($resulting < 0) {
                throw new InsufficientStock($item, abs($delta), (float) $item->quantity);
            }

            // client_id/order_id diambil dari ITEM, bukan dari input pemanggil.
            // Inilah yang membuat FK komposit selalu dapat dipenuhi oleh jalur
            // resmi, sehingga pelanggarannya hanya mungkin terjadi bila kode
            // melewati service ini.
            $movement = InventoryMovement::create([
                'client_id' => $item->client_id,
                'order_id' => $item->order_id,
                'inventory_item_id' => $item->getKey(),
                'movement_type' => $type,
                'quantity' => $delta,
                'reference' => $reference,
                'notes' => $notes,
                'created_by' => auth()->id(),
                'recorded_at' => now(),
            ]);

            $item->update(['quantity' => $resulting]);

            return $movement;
        });
    }

    /**
     * Mencatat sisa potongan / scrap beserta kategorinya (FR-008).
     */
    public function recordWaste(
        Order $order,
        ?Material $material,
        float $quantity,
        ?string $category = null,
        ?string $notes = null,
    ): WasteRecord {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Jumlah sisa potongan harus lebih besar dari nol.');
        }

        return DB::transaction(fn (): WasteRecord => WasteRecord::create([
            'client_id' => $order->client_id,
            'order_id' => $order->getKey(),
            'material_id' => $material?->getKey(),
            'quantity' => $quantity,
            'uom' => $material?->uom ?? 'meter',
            'category' => $category,
            'notes' => $notes,
            'recorded_by' => auth()->id(),
        ]));
    }

    private function assertQuantityIsSane(MovementType $type, float $quantity): void
    {
        if ($quantity === 0.0) {
            throw new InvalidArgumentException('Jumlah pergerakan stok tidak boleh nol.');
        }

        // Penyesuaian (stock opname) boleh bernilai negatif untuk menurunkan
        // stok; pergerakan lain selalu dimasukkan sebagai besaran positif.
        if ($quantity < 0 && $type !== MovementType::ADJUSTMENT) {
            throw new InvalidArgumentException('Hanya penyesuaian (stock opname) yang boleh bernilai negatif.');
        }
    }

    /**
     * FR-007: pemilik bahan dan pesanan tujuan harus cocok sebelum dicatat.
     */
    private function assertDestinationMatches(InventoryItem $item, MovementType $type, ?Order $destination): void
    {
        if (! $type->requiresOwnershipMatch()) {
            return;
        }

        if ($destination === null) {
            throw new InvalidArgumentException('Pengeluaran ke cutting dan transfer wajib menyebutkan pesanan tujuan.');
        }

        if (! $item->belongsToOrderOf($destination->client_id, $destination->getKey())) {
            throw InventoryOwnershipMismatch::for($item, $destination->client_id, $destination);
        }
    }

    /**
     * Perubahan bertanda yang dibawa pergerakan ini ke jumlah stok.
     */
    private function signedDelta(MovementType $type, float $quantity): float
    {
        return match ($type) {
            MovementType::RECEIPT, MovementType::FG_IN => abs($quantity),
            MovementType::ISSUE_TO_CUTTING, MovementType::TRANSFER => -abs($quantity),
            // Sudah bertanda: penyesuaian dapat menambah maupun mengurangi.
            MovementType::ADJUSTMENT => $quantity,
        };
    }
}
