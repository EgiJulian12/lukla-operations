<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\InventoryMovement;
use App\Models\User;

/**
 * Otorisasi pergerakan stok (FR-006, FR-007).
 *
 * Pergerakan bersifat catatan peristiwa: hanya boleh DIBUAT, tidak pernah
 * diubah atau dihapus. Koreksi dilakukan dengan mencatat pergerakan
 * penyesuaian baru, sehingga jejak pergerakan tetap utuh (NFR-005).
 */
class InventoryMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageInventory()
            || $role->canConfigureSystem()
            || $role->canManagePayroll()
            || $role->canManageSamples()
            || $role->canManageProduction()
            || $role->isClientFacing()
        );
    }

    public function view(User $user, InventoryMovement $movement): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $movement->client_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageInventory());
    }

    public function update(User $user, InventoryMovement $movement): bool
    {
        return false;
    }

    public function delete(User $user, InventoryMovement $movement): bool
    {
        return false;
    }
}
