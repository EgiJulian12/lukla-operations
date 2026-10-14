<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\DefectCategory;
use App\Models\User;

/**
 * Referensi kategori cacat (matriks RBAC, QC).
 *
 * Data referensi global yang dipakai lintas klien, jadi otorisasinya berbasis
 * peran saja, bukan kepemilikan klien.
 */
class DefectCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageProduction()
            || $role->canConfigureSystem()
        );
    }

    public function view(User $user, DefectCategory $defectCategory): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, DefectCategory $defectCategory): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, DefectCategory $defectCategory): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageProduction()
            || $role->canConfigureSystem()
        );
    }
}
