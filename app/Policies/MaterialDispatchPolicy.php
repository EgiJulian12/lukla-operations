<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MaterialDispatch;
use App\Models\User;

/**
 * Otorisasi pelacakan pengantaran bahan ke produksi.
 *
 * Aturan bisnis:
 * 1. Bagian Produksi (PRODUCTION_LEAD, OWNER_EXECUTIVE) mengajukan permintaan bahan (canCreate).
 * 2. Bagian Gudang (WAREHOUSE) HANYA BISA MELIHAT & MEMPROSES (approve/dispatch/deliver), TIDAK BISA MENGAJUKAN.
 */
class MaterialDispatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageInventory()
            || $role->canManageProduction()
            || $role->canConfigureSystem()
        );
    }

    public function view(User $user, MaterialDispatch $dispatch): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $dispatch->client_id;
    }

    /**
     * Gudang TIDAK BISA mengajukan permintaan bahan — permintaan bahan HANYA
     * diajukan oleh bagian produksi yang membutuhkan bahan di lini.
     */
    public function create(User $user): bool
    {
        // Bila user HANYA memiliki peran Gudang (tanpa peran produksi/eksekutif), tolak pembuatan
        if ($user->hasRole(UserRole::WAREHOUSE) && ! $user->hasRole(UserRole::PRODUCTION_LEAD) && ! $user->hasRole(UserRole::OWNER_EXECUTIVE)) {
            return false;
        }

        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageProduction()
            || $role->canConfigureSystem()
        );
    }

    public function update(User $user, MaterialDispatch $dispatch): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageInventory()
            || $role->canManageProduction()
            || $role->canConfigureSystem()
        );
    }

    public function delete(User $user, MaterialDispatch $dispatch): bool
    {
        return false;
    }

    public function restore(User $user, MaterialDispatch $dispatch): bool
    {
        return false;
    }

    public function forceDelete(User $user, MaterialDispatch $dispatch): bool
    {
        return false;
    }
}
