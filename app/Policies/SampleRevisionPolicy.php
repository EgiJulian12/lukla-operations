<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SampleRevision;
use App\Models\User;

/**
 * Revisi sample bersifat append-only (FR-001, BR-005).
 *
 * Menyunting spesifikasi dilakukan dengan membuat revisi baru, bukan menimpa
 * yang lama, sehingga revisi yang sudah disetujui terkunci dan tidak ada revisi
 * yang boleh dihapus.
 */
class SampleRevisionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SampleRevision $revision): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $revision->client_id;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, SampleRevision $revision): bool
    {
        return $this->canManage($user) && ! $revision->isApproved();
    }

    public function delete(User $user, SampleRevision $revision): bool
    {
        return false;
    }

    public function approve(User $user, SampleRevision $revision): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canApproveSamples());
    }

    public function reject(User $user, SampleRevision $revision): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canApproveSamples());
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageSamples());
    }
}
