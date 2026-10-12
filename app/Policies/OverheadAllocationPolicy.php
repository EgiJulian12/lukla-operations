<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OverheadAllocation;
use App\Models\User;

/**
 * Alokasi overhead per unit adalah laporan turunan (FR-018).
 *
 * Selalu hanya-baca: nilainya dihitung lewat PayrollService, dan hanya terlihat
 * oleh Finance serta Owner.
 */
class OverheadAllocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, OverheadAllocation $allocation): bool
    {
        return $this->canView($user);
    }

    private function canView(User $user): bool
    {
        return $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role->canManagePayroll() || $role->canConfigureSystem(),
        );
    }
}
