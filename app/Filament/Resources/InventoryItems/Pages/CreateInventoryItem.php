<?php

namespace App\Filament\Resources\InventoryItems\Pages;

use App\Enums\InventoryState;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Models\Material;
use App\Models\Order;
use App\Services\Inventory\InventoryService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Penerimaan bahan (FR-005).
 *
 * Pembuatan item TIDAK dilakukan langsung dari data formulir. Semuanya
 * dialihkan ke InventoryService::receive() supaya client_id diambil dari
 * pesanan (bukan dari input pengguna) dan jumlah awal tercatat sebagai
 * pergerakan RECEIPT — sehingga "tidak ada item yatim di gudang" dijaga oleh
 * satu jalur tulis saja.
 */
class CreateInventoryItem extends CreateRecord
{
    protected static string $resource = InventoryItemResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * State formulir dapat berupa instance enum (options-nya
     * InventoryState::class) maupun nilai mentah dari basis data.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(InventoryService::class)->receive(
            order: Order::findOrFail($data['order_id']),
            material: Material::findOrFail($data['material_id']),
            quantity: (float) $data['quantity'],
            batch: $data['batch'] ?? null,
            location: $data['location'] ?? null,
            state: $data['state'] instanceof InventoryState
                ? $data['state']
                : InventoryState::from($data['state']),
        );
    }
}
