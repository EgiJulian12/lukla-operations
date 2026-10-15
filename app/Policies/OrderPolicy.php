<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

/**
 * Order — permintaan klien sampai produksi massal (FR-004).
 *
 * Order yang selesai atau dibatalkan terkunci dan tidak boleh diubah lagi
 * (matriks RBAC, histori transaksi terkunci). Order juga tidak dihapus:
 * pembatalan dicatat lewat status.
 *
 * Klien hanya menyentuh order miliknya sendiri, dan hanya selama masih berupa
 * permintaan — persetujuan penawaran termasuk di dalamnya.
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
        return $this->canManage($user)
            || $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->isClientFacing());
    }

    public function update(User $user, Order $order): bool
    {
        if ($order->isLocked()) {
            return false;
        }

        if ($this->canManage($user)) {
            return true;
        }

        return $this->ownsAsClient($user, $order) && $order->awaitingQuotation();
    }

    public function delete(User $user, Order $order): bool
    {
        return false;
    }

    /**
     * Penawaran diberikan owner saat order masih berupa permintaan klien.
     */
    public function quote(User $user, Order $order): bool
    {
        return $this->canManage($user) && $order->status->needsQuotation();
    }

    /**
     * Persetujuan penawaran adalah hak klien pemilik order (FR-025).
     */
    public function approveQuote(User $user, Order $order): bool
    {
        return $this->ownsAsClient($user, $order) && $order->status->awaitingClientApproval();
    }

    /**
     * Penolakan penawaran adalah hak klien pemilik order saat penawaran diajukan (FR-025).
     */
    public function rejectQuote(User $user, Order $order): bool
    {
        return $this->ownsAsClient($user, $order) && $order->status->awaitingClientApproval();
    }

    /**
     * Penolakan pesanan pada tahap permintaan/penawaran adalah wewenang owner (FR-004).
     */
    public function rejectRequest(User $user, Order $order): bool
    {
        return $this->canManage($user) && in_array($order->status, [OrderStatus::REQUESTED, OrderStatus::QUOTED], true);
    }

    /**
     * Permintaan sample hanya setelah penawaran disetujui, dan hanya sekali.
     */
    public function requestSample(User $user, Order $order): bool
    {
        return $this->canManage($user)
            && $order->status->isQuoteApproved()
            && $order->sample_id === null;
    }

    /**
     * Membuat master artikel dan inisialisasi sample v1 dari order adalah hak eksklusif Tim Sample (FR-001, FR-004).
     */
    public function createArticleAndSample(User $user, Order $order): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageSamples())
            && $order->article_id === null
            && in_array($order->status, [OrderStatus::QUOTE_APPROVED, OrderStatus::QUOTED, OrderStatus::REQUESTED], true);
    }

    /**
     * Memulai produksi massal adalah wewenang Kepala Produksi, dan hanya untuk
     * order yang sudah berstatus Siap Produksi (FR-004 → FR-009). Peran yang
     * memelihara order (Tim Sample, Owner) tidak mengerjakan lantai produksi.
     */
    public function startProduction(User $user, Order $order): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageProduction())
            && $order->status === OrderStatus::READY_FOR_PRODUCTION;
    }

    /**
     * Mengubah pemetaan penjahit per komponen adalah wewenang Kepala Produksi,
     * dan hanya berlaku selama order masih berstatus Dalam Produksi.
     */
    public function updateComponentAssignments(User $user, Order $order): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageProduction())
            && $order->status === OrderStatus::IN_PRODUCTION;
    }

    private function canManage(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageOrders());
    }

    private function ownsAsClient(User $user, Order $order): bool
    {
        return $user->client_id !== null
            && (int) $user->client_id === (int) $order->client_id;
    }
}
