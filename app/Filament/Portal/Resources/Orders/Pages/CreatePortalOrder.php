<?php

namespace App\Filament\Portal\Resources\Orders\Pages;

use App\Filament\Portal\Resources\Orders\PortalOrderResource;
use App\Models\User;
use App\Services\Order\OrderQuotationService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Klien mengajukan permintaan order baru.
 *
 * Pembuatan record dialihkan ke service agar pemilik order selalu diambil dari
 * akun klien yang login, bukan dari data formulir (FR-025).
 */
class CreatePortalOrder extends CreateRecord
{
    protected static string $resource = PortalOrderResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        return app(OrderQuotationService::class)->submitRequest(
            $user->client,
            $data['design_images'] ?? [],
            (string) $data['description'],
            null,
            $user,
        );
    }
}
