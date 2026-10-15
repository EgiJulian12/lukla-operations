<?php

namespace App\Filament\Portal\Resources\Orders\Pages;

use App\Enums\WorkScheme;
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

        $workScheme = null;
        if (isset($data['work_scheme'])) {
            $workScheme = $data['work_scheme'] instanceof WorkScheme
                ? $data['work_scheme']
                : WorkScheme::tryFrom((string) $data['work_scheme']);
        }

        return app(OrderQuotationService::class)->submitRequest(
            $user->client,
            $data['design_images'] ?? [],
            (string) $data['description'],
            null,
            $user,
            $workScheme ?? WorkScheme::CMT,
            $data['due_date'] ?? null,
            isset($data['quantity']) ? (int) $data['quantity'] : null,
        );
    }
}
