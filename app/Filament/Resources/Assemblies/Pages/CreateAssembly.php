<?php

namespace App\Filament\Resources\Assemblies\Pages;

use App\Exceptions\AssemblyNotReady;
use App\Filament\Resources\Assemblies\AssemblyResource;
use App\Models\Order;
use App\Services\Production\ProductionService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Membuat perakitan lewat ProductionService (FR-013).
 *
 * Service memverifikasi bahwa seluruh output yang dipilih sudah terverifikasi
 * dan benar-benar milik order yang sama, serta bahwa ordernya sendiri sudah
 * siap dirakit. Order yang belum siap adalah keadaan yang bisa dipilih
 * pengguna, bukan kegagalan sistem, sehingga pesannya dikembalikan sebagai
 * galat validasi — sejalan dengan halaman verifikasi order yang menampilkan
 * alasan serupa di dalam modalnya.
 */
class CreateAssembly extends CreateRecord
{
    protected static string $resource = AssemblyResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ProductionService::class)->assemble(
                order: Order::findOrFail($data['order_id']),
                outputEntryIds: $data['output_entry_ids'] ?? [],
                quantity: (int) $data['quantity'],
                notes: $data['notes'] ?? null,
            );
        } catch (AssemblyNotReady $exception) {
            throw ValidationException::withMessages([
                'data.order_id' => $exception->getMessage(),
            ]);
        }
    }
}
