<?php

namespace App\Exceptions;

use App\Models\PurchaseOrder;
use RuntimeException;

/**
 * PO bahan tidak berada dalam keadaan yang memungkinkan aksi yang diminta
 * (matriks RBAC SRS, Finance).
 */
class InvalidPurchaseOrderState extends RuntimeException
{
    public static function notApprovable(PurchaseOrder $order): self
    {
        return new self(sprintf(
            'PO %s sudah %s, sehingga tidak dapat disetujui lagi.',
            $order->po_number,
            $order->status->getLabel(),
        ));
    }
}
