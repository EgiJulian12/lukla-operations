<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Component;
use App\Models\User;

/**
 * Komponen artikel (matriks RBAC, Tim Sample).
 *
 * Komponen tidak membawa client_id sendiri; kepemilikannya mengikuti artikel
 * induk, sehingga otorisasi cukup berbasis peran.
 */
class ComponentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Component $component): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Component $component): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Component $component): bool
    {
        return $this->canManage($user);
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageArticles());
    }
}
