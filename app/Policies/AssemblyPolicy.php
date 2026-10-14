<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Assembly;
use App\Models\User;

/**
 * Perakitan produk (FR-013).
 *
 * Perakitan adalah catatan peristiwa: dibuat sekali dari output terverifikasi,
 * tidak disunting dan tidak dihapus. Koreksi dilakukan dengan output atau
 * perakitan baru.
 */
class AssemblyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageProduction()
            || $role->canConfigureSystem()
        );
    }

    public function view(User $user, Assembly $assembly): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $assembly->client_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageProduction());
    }

    public function update(User $user, Assembly $assembly): bool
    {
        return false;
    }

    public function delete(User $user, Assembly $assembly): bool
    {
        return false;
    }
}
