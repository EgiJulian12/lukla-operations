<?php

namespace App\Filament\Resources\MaterialDispatches\Pages;

use App\Exceptions\InsufficientStock;
use App\Filament\Resources\MaterialDispatches\MaterialDispatchResource;
use App\Models\InventoryItem;
use App\Services\Inventory\MaterialDispatchService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateMaterialDispatch extends CreateRecord
{
    protected static string $resource = MaterialDispatchResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $item = InventoryItem::findOrFail($data['inventory_item_id']);

        try {
            return app(MaterialDispatchService::class)->createRequest(
                item: $item,
                quantity: (float) $data['quantity'],
                requestedBy: auth()->user() ?? 1,
                notes: $data['notes'] ?? null,
            );
        } catch (InsufficientStock $exception) {
            throw ValidationException::withMessages([
                'data.quantity' => $exception->getMessage(),
            ]);
        }
    }
}
