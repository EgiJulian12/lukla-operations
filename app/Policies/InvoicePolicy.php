<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;

/**
 * Invoice klien (matriks RBAC SRS, Finance).
 *
 * Invoice adalah dokumen keuangan internal; klien tidak melihatnya di portal,
 * sehingga hanya peran internal yang berwenang. Invoice yang sudah terbit
 * terkunci dan koreksinya memakai dokumen baru (NFR-005).
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->canManage($user) && ! $invoice->isLocked();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->canManage($user)
            && $invoice->status === InvoiceStatus::DRAFT;
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $this->canManage($user)
            && $invoice->status === InvoiceStatus::ISSUED;
    }

    private function canView(User $user): bool
    {
        return $user->client_id === null && $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role->canManageInvoices() || $role->canConfigureSystem(),
        );
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role->canManageInvoices(),
        );
    }
}
