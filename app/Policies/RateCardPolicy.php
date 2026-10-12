<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\RateCard;
use App\Models\User;

/**
 * Rate card ongkos potong/jahit (FR-010).
 *
 * Tarif per komponen ditentukan Kepala Produksi, Finance, dan Owner (matriks
 * RBAC SRS). Tarif lama tidak dihapus, melainkan ditutup dengan effective_to
 * supaya payroll periode lama dapat dihitung ulang dengan tarif saat itu.
 */
class RateCardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
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

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageRateCards());
    }
}
