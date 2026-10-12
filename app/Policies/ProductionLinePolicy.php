<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ProductionLine;
use App\Models\User;

/**
 * Line produksi (matriks RBAC, Kepala Produksi).
 *
 * Line adalah fasilitas pabrik yang melayani order lintas klien, jadi bukan
 * data per-klien dan otorisasinya berbasis peran.
 */
class ProductionLinePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductionLine $line): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, ProductionLine $line): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, ProductionLine $line): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageProduction());
    }
}
