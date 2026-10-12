<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WasteRecord;

/**
 * Otorisasi catatan sisa potongan / scrap (FR-008).
 *
 * Dicatat oleh Gudang saat cutting berjalan, dan hanya boleh dilihat klien
 * pemilik bahannya untuk pelaporan efisiensi per klien.
 */
class WasteRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, WasteRecord $record): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $record->client_id;
    }

    public function create(User $user): bool
    {
        return $this->canRecordWaste($user);
    }

    public function update(User $user, WasteRecord $record): bool
    {
        return $this->canRecordWaste($user);
    }

    public function delete(User $user, WasteRecord $record): bool
    {
        return false;
    }

    private function canRecordWaste(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canRecordWaste());
    }
}
