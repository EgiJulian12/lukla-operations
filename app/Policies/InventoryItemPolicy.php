<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\InventoryItem;
use App\Models\User;

/**
 * Otorisasi stok gudang (matriks RBAC — Gudang).
 *
 * Policy ini menjawab "siapa yang boleh bertindak". Kecocokan klien/pesanan
 * sengaja TIDAK diurus di sini: itu bukan soal izin melainkan soal data, dan
 * ditegakkan InventoryService di atas FK komposit inventory_movements.
 *
 * Aturan Finance "tidak dapat mengubah fisik stok di gudang" dan QC/Penjahit
 * "tidak menyentuh gudang" diwakili oleh canManageInventory() yang hanya
 * benar untuk peran Gudang — tanpa perlu aturan khusus per peran.
 */
class InventoryItemPolicy
{
    /**
     * Semua peran boleh melihat daftar stok. Untuk akun klien, daftar tersebut
     * sudah terpotong ke client_id sendiri oleh global scope BelongsToClient.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageInventory()
            || $role->canConfigureSystem()
            || $role->canManagePayroll()
            || $role->canManageSamples()
            || $role->canManageProduction()
            || $role->isClientFacing()
        );
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $user->client_id === null
            || (int) $user->client_id === (int) $item->client_id;
    }

    public function create(User $user): bool
    {
        return $this->canManageInventory($user);
    }

    public function update(User $user, InventoryItem $item): bool
    {
        // Barang yang sudah diterima oleh produksi artinya sudah keluar dari gudang dan tidak dapat diedit lagi
        if ($item->isReceivedByProduction()) {
            return false;
        }

        return $this->canManageInventory($user);
    }

    /**
     * Stok tidak pernah dihapus — koreksi dilakukan lewat pergerakan
     * penyesuaian (stock opname) agar riwayat tetap utuh (NFR-005).
     */
    public function delete(User $user, InventoryItem $item): bool
    {
        return false;
    }

    public function restore(User $user, InventoryItem $item): bool
    {
        return false;
    }

    public function forceDelete(User $user, InventoryItem $item): bool
    {
        return false;
    }

    private function canManageInventory(User $user): bool
    {
        return $user->hasRoleSatisfying(fn (UserRole $role): bool => $role->canManageInventory());
    }
}
