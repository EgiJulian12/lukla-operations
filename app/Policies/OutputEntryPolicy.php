<?php

namespace App\Policies;

use App\Enums\OutputStatus;
use App\Enums\UserRole;
use App\Models\OutputEntry;
use App\Models\User;

/**
 * Output 30 menit (FR-011, FR-012).
 *
 * Penjahit melaporkan pekerjaannya sendiri; mandor boleh mencatat untuk siapa
 * pun dan memverifikasi. Output yang sudah diverifikasi terkunci, sehingga
 * penjahit tidak dapat mengeditnya kembali (matriks RBAC).
 */
class OutputEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canSubmitOutput()
            || $role->canVerifyOutput()
            || $role->canManageProduction()
            || $role->canConfigureSystem()
            || $role->canManagePayroll()
        );
    }

    public function view(User $user, OutputEntry $entry): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $entry->client_id;
    }

    public function create(User $user): bool
    {
        return $this->canSubmit($user) || $this->canManageProduction($user);
    }

    public function update(User $user, OutputEntry $entry): bool
    {
        if ($entry->isLocked()) {
            return false;
        }

        return $this->canManageProduction($user) || (int) $entry->user_id === (int) $user->getKey();
    }

    public function delete(User $user, OutputEntry $entry): bool
    {
        return $this->canManageProduction($user) && ! $entry->isLocked();
    }

    /**
     * Verifikasi adalah wewenang mandor, terpisah dari pencatat output
     * (FR-012, separasi tugas).
     */
    public function verify(User $user, OutputEntry $entry): bool
    {
        return $this->canVerify($user) && (int) $entry->user_id !== (int) $user->getKey();
    }

    public function reject(User $user, OutputEntry $entry): bool
    {
        return $this->canVerify($user) && (int) $entry->user_id !== (int) $user->getKey();
    }

    public function resubmit(User $user, OutputEntry $entry): bool
    {
        return $entry->status === OutputStatus::REJECTED
            && ((int) $entry->user_id === (int) $user->getKey() || $this->canManageProduction($user));
    }

    private function canSubmit(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canSubmitOutput());
    }

    private function canVerify(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canVerifyOutput());
    }

    private function canManageProduction(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageProduction());
    }
}
