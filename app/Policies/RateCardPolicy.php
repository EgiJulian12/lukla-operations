<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RateCard;
use App\Models\User;

/**
 * Rate card ongkos potong/jahit (FR-010).
 *
 * Tarif per komponen diusulkan Kepala Produksi, namun disetujui (approve)
 * oleh Owner atau Finance agar terjaga dari kebocoran plafon kontrak CMT.
 */
class RateCardPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, RateCard $rateCard): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $rateCard->client_id;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, RateCard $rateCard): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, RateCard $rateCard): bool
    {
        return false;
    }

    public function approve(User $user, RateCard $rateCard): bool
    {
        return $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role->canConfigureSystem() || $role->canManagePayroll(),
        );
    }

    public function reject(User $user, RateCard $rateCard): bool
    {
        return $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role->canConfigureSystem() || $role->canManagePayroll(),
        );
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageRateCards());
    }
}
