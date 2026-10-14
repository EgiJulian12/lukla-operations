<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LineAssignment;
use App\Models\User;

/**
 * Penugasan penjahit ke line (FR-009).
 *
 * Penugasan ditutup dengan mengisi unassigned_at, bukan dihapus, sehingga
 * riwayat alokasi tetap dapat diaudit.
 */
class LineAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user) || $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canConfigureSystem());
    }

    public function view(User $user, LineAssignment $assignment): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, LineAssignment $assignment): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, LineAssignment $assignment): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageProduction());
    }
}
