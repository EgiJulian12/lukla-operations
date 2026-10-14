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
 *
 * Line bersifat opsional — penugasan penjahit ditetapkan per komponen, bukan
 * lewat keanggotaan line — sehingga baris tanpa line tetap boleh dicatat.
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
                // Line boleh kosong, tetapi bila diisi harus menunjuk line yang
                // benar-benar ada — findOrFail menjaga salah isi tetap terlihat
                // sebagai galat, bukan diam-diam tercatat tanpa line.
                line: filled($data['production_line_id'] ?? null)
                    ? ProductionLine::findOrFail($data['production_line_id'])
                    : null,
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
