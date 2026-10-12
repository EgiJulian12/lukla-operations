<?php

namespace App\Filament\Resources\Assemblies\Pages;

use App\Filament\Resources\Assemblies\AssemblyResource;
use App\Models\Order;
use App\Services\Production\ProductionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Membuat perakitan lewat ProductionService (FR-013).
 *
 * Service memverifikasi bahwa seluruh output yang dipilih sudah terverifikasi
 * dan benar-benar milik order yang sama.
 */
class CreateAssembly extends CreateRecord
{
    protected static string $resource = AssemblyResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ProductionService::class)->assemble(
            order: Order::findOrFail($data['order_id']),
            outputEntryIds: $data['output_entry_ids'] ?? [],
            quantity: (int) $data['quantity'],
            notes: $data['notes'] ?? null,
        );
    }
}
