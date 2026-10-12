<?php

namespace App\Exceptions;

use App\Models\InventoryItem;
use RuntimeException;

/**
 * Jumlah stok tidak cukup untuk pergerakan keluar yang diminta.
 *
 * Ditegakkan sebelum transaksi ditulis, sehingga stok tidak pernah menjadi
 * negatif — termasuk saat penyesuaian (stock opname) menurunkan jumlah.
 */
class InsufficientStock extends RuntimeException
{
    public function __construct(
        public readonly InventoryItem $item,
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct(sprintf(
            'Stok bahan #%d hanya %s %s, tidak cukup untuk mengeluarkan %s %s.',
            $item->getKey(),
            $available,
            $item->uom,
            $requested,
            $item->uom,
        ));
    }
}
