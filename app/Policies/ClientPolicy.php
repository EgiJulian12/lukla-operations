<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;

/**
 * Data induk klien (matriks RBAC, Owner dan Finance).
 *
 * Klien adalah akar multi-tenant, sehingga akun klien hanya boleh melihat
 * barisnya sendiri. Penghapusan bersifat lunak agar order dan stok lama tetap
 * dapat ditelusuri.
 */
class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Client $client): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $client->getKey();
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Client $client): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageClients());
    }
}
