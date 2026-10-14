<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

/**
 * Order produksi massal (FR-004).
 *
 * Order yang selesai atau dibatalkan terkunci dan tidak boleh diubah lagi
 * (matriks RBAC, histori transaksi terkunci). Order juga tidak dihapus:
 * pembatalan dicatat lewat status.
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canConfigureSystem()
            || $role->canManageOrders()
            || $role->canManageProduction()
            || $role->canManageInventory()
            || $role->canManageInvoices()
            || $role->isClientFacing()
        );
    }

    public function view(User $user, Order $order): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $order->client_id;
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Order $order): bool
    {
        return $this->canManage($user) && ! $order->isLocked();
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageOrders());
    }
}
