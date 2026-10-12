<?php

namespace App\Services\Finance;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidPurchaseOrderState;
use App\Models\PurchaseOrder;

/**
 * Satu-satunya jalur tulis untuk persetujuan PO bahan (matriks RBAC SRS,
 * Finance). Perubahan kolom biasa tetap terekam jejak audit lewat trait
 * Auditable pada model, jadi service ini tidak mencatatnya sendiri.
 */
class PurchaseOrderService
{
    /**
     * Menyetujui PO bahan sehingga pesanan ke pemasok dianggap sah.
     */
    public function approve(PurchaseOrder $order): PurchaseOrder
    {
        if (! $order->status->isApprovable()) {
            throw InvalidPurchaseOrderState::notApprovable($order);
        }

        $order->update([
            'status' => PurchaseOrderStatus::APPROVED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $order->refresh();
    }
}
