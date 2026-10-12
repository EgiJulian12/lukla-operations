<?php

namespace App\Filament\Resources\OutputEntries\Pages;

use App\Exceptions\DuplicateOutputInterval;
use App\Filament\Resources\OutputEntries\OutputEntryResource;
use App\Models\Component;
use App\Models\Order;
use App\Models\ProductionLine;
use App\Models\User;
use App\Services\Production\ProductionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Mencatat output lantai produksi lewat ProductionService (FR-011).
 *
 * Bila penjahit sudah punya catatan untuk komponen dan interval yang sama,
 * service melempar DuplicateOutputInterval dan halaman ini mengubahnya menjadi
 * galat validasi pada field waktu interval.
 */
class CreateOutputEntry extends CreateRecord
{
    protected static string $resource = OutputEntryResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ProductionService::class)->submitOutput(
                order: Order::findOrFail($data['order_id']),
                line: ProductionLine::findOrFail($data['production_line_id']),
                operator: User::findOrFail($data['user_id']),
                component: Component::findOrFail($data['component_id']),
                quantity: (int) $data['quantity'],
                intervalAt: $data['interval_at'],
                notes: $data['notes'] ?? null,
            );
        } catch (DuplicateOutputInterval $exception) {
            throw ValidationException::withMessages([
                'data.interval_at' => $exception->getMessage(),
            ]);
        }
    }
}
