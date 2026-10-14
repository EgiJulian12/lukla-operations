<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Repair;
use App\Models\User;

/**
 * Otorisasi modul Repair/Perbaikan (FR-014, BR 4.6).
 *
 * Tiket repair dapat dipantau dan dikelola bersama oleh QC Inspector
 * dan Kepala Produksi (serta Owner).
 */
class RepairPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function view(User $user, Repair $repair): bool
    {
        return $this->canAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function update(User $user, Repair $repair): bool
    {
        return $this->canAccess($user);
    }

    public function delete(User $user, Repair $repair): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canConfigureSystem());
    }

    private function canAccess(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canInspectQuality()
            || $role->canManageProduction()
            || $role->canConfigureSystem()
        );
    }
}
