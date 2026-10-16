<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QcRecord;
use App\Models\User;

/**
 * Hasil inspeksi QC (FR-014).
 *
 * Keputusan QC adalah catatan peristiwa yang tidak disunting maupun dihapus
 * (NFR-005). Hanya QC inspector yang boleh mencatatnya, dan QC tidak boleh
 * mengubah data order atau harga borongan klien (matriks RBAC).
 */
class QcRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canInspectQuality()
            || $role->canManageProduction()
            || $role->canConfigureSystem()
            || $role->canManagePayroll()
            || $role->isClientFacing()
        );
    }

    public function view(User $user, QcRecord $record): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $record->client_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canInspectQuality());
    }

    public function update(User $user, QcRecord $record): bool
    {
        return false;
    }

    public function delete(User $user, QcRecord $record): bool
    {
        return false;
    }
}
