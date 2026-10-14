<?php

namespace App\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Enums\UserRole;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * PO bahan (matriks RBAC SRS, Finance).
 *
 * PO adalah dokumen pengadaan internal pabrik, sehingga hanya peran internal
 * yang boleh melihatnya. PO yang sudah disetujui terkunci dan tidak disunting
 * lagi (NFR-005).
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canView($user);
    }

    public function view(User $user, PurchaseOrder $order): bool
    {
        return $this->canView($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, PurchaseOrder $order): bool
    {
        return $this->canManage($user) && ! $order->isLocked();
    }

    public function delete(User $user, PurchaseOrder $order): bool
    {
        return false;
    }

    /**
     * Persetujuan hanya masuk akal selama PO masih draf (matriks RBAC SRS).
     */
    public function approve(User $user, PurchaseOrder $order): bool
    {
        return $this->canManage($user)
            && $order->status !== null
            && $order->status === PurchaseOrderStatus::DRAFT;
    }

    private function canView(User $user): bool
    {
        return $user->client_id === null && $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role->canManagePurchaseOrders() || $role->canConfigureSystem(),
        );
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(
            fn (UserRole $role): bool => $role->canManagePurchaseOrders(),
        );
    }
}
