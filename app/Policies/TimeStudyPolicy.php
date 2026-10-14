<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TimeStudy;
use App\Models\User;

/**
 * Time study adalah catatan pengukuran (FR-002), bukan data yang disunting.
 *
 * Hanya boleh DIBUAT. Koreksi dilakukan dengan mencatat pengukuran baru,
 * sejalan dengan perlakuan ledger pada modul stok.
 */
class TimeStudyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageSamples()
            || $role->canConfigureSystem()
            || $role->canManageProduction()
        );
    }

    public function view(User $user, TimeStudy $timeStudy): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $timeStudy->client_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageSamples());
    }

    public function update(User $user, TimeStudy $timeStudy): bool
    {
        return false;
    }

    public function delete(User $user, TimeStudy $timeStudy): bool
    {
        return false;
    }
}
