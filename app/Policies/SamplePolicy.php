<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Sample;
use App\Models\User;

/**
 * Siklus hidup sample (FR-003, FR-004).
 *
 * Tim Sample membuat, merevisi, dan menyetujui versi sample (matriks RBAC SRS);
 * Owner juga berwenang menyetujui. Konversi ke order massal hanya sah untuk
 * sample yang sudah punya revisi disetujui.
 */
class SamplePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageSamples()
            || $role->canApproveSamples()
            || $role->canConfigureSystem()
            || $role->canManageProduction()
            || $role->canManagePayroll()
            || $role->canManageRateCards()
            || $role->isClientFacing()
        );
    }

    public function view(User $user, Sample $sample): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $sample->client_id;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Sample $sample): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Sample $sample): bool
    {
        return $this->canManage($user);
    }

    /**
     * Persetujuan revisi sample (FR-003, FR-026).
     */
    public function approve(User $user, Sample $sample): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canApproveSamples());
    }

    public function reject(User $user, Sample $sample): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canApproveSamples());
    }

    /**
     * Konversi ke order produksi massal (FR-004).
     */
    public function convertToOrder(User $user, Sample $sample): bool
    {
        $canManage = $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageOrders());

        return $canManage && $sample->isReadyForMassProduction();
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageSamples());
    }
}
