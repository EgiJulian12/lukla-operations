<?php

namespace App\Filament\Resources\WasteRecords\Pages;

use App\Filament\Resources\WasteRecords\WasteRecordResource;
use App\Models\Material;
use App\Models\Order;
use App\Services\Inventory\InventoryService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Mencatat sisa potongan lewat InventoryService (FR-008).
 *
 * Klien diambil dari pesanan di dalam service, bukan dari formulir, sehingga
 * catatan sisa potongan tidak dapat diatribusikan ke klien yang salah.
 */
class CreateWasteRecord extends CreateRecord
{
    protected static string $resource = WasteRecordResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(InventoryService::class)->recordWaste(
            order: Order::findOrFail($data['order_id']),
            material: isset($data['material_id']) ? Material::find($data['material_id']) : null,
            quantity: (float) $data['quantity'],
            category: $data['category'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }
}
