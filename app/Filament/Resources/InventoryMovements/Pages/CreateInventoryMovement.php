<?php

namespace App\Filament\Resources\InventoryMovements\Pages;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStock;
use App\Exceptions\InventoryOwnershipMismatch;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Services\Inventory\InventoryService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Mencatat pergerakan stok lewat InventoryService — satu-satunya jalur tulis (FR-006, FR-007).
 *
 * Di sinilah peringatan anti-kontaminasi silang sampai ke pengguna. Ketika
 * petugas memilih pesanan tujuan milik klien lain, InventoryService melempar
 * InventoryOwnershipMismatch dan halaman ini mengubahnya menjadi galat
 * validasi pada field tujuan — sehingga pesan muncul pada aksi yang sama,
 * sebelum ada baris yang tercatat (NFR-002).
 *
 * Perhatikan: pemeriksaan di atas tetap akan ditegakkan PostgreSQL lewat FK
 * komposit inventory_movements bila jalur ini dilewati.
 */
class CreateInventoryMovement extends CreateRecord
{
    protected static string $resource = InventoryMovementResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $type = $data['movement_type'] instanceof MovementType
            ? $data['movement_type']
            : MovementType::from($data['movement_type']);

        $destination = $type->requiresOwnershipMatch()
            ? Order::findOrFail($data['destination_order_id'])
            : null;

        try {
            return app(InventoryService::class)->recordMovement(
                item: InventoryItem::findOrFail($data['inventory_item_id']),
                type: $type,
                quantity: (float) $data['quantity'],
                destination: $destination,
                reference: $data['reference'] ?? null,
                notes: $data['notes'] ?? null,
            );
        } catch (InventoryOwnershipMismatch $exception) {
            throw ValidationException::withMessages([
                'data.destination_order_id' => $exception->getMessage(),
            ]);
        } catch (InsufficientStock $exception) {
            throw ValidationException::withMessages([
                'data.quantity' => $exception->getMessage(),
            ]);
        }
    }
}
