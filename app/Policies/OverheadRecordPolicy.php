<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\OverheadRecord;
use App\Models\User;

/**
 * Biaya overhead bulanan (FR-018).
 *
 * Input biaya pabrik, bukan data per-klien, sehingga otorisasinya Finance dan
 * Owner saja.
 */
class OverheadRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, OverheadRecord $record): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, OverheadRecord $record): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, OverheadRecord $record): bool
    {
        return $this->canManage($user);
    }

    private function canView(User $user): bool
    {
        return $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role->canManagePayroll() || $role->canConfigureSystem(),
        );
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManagePayroll());
    }
}
