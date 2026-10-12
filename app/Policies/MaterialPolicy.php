<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Material;
use App\Models\User;

/**
 * Data induk bahan (matriks RBAC, Gudang).
 *
 * Bahan generik (client_id null) dipakai lintas klien sehingga terlihat oleh
 * semua peran. Bahan titipan hanya terlihat pemiliknya atau staf internal.
 */
class MaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Material $material): bool
    {
        return $user->client_id === null
            || $material->client_id === null
            || (int) $user->client_id === (int) $material->client_id;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Material $material): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Material $material): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageMaterials());
    }
}
