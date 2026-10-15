<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;

/**
 * Order tidak berada dalam keadaan yang memungkinkan aksi produksi yang diminta
 * (FR-004 → FR-009).
 */
class InvalidOrderState extends RuntimeException
{
    public static function notReadyForProduction(Order $order): self
    {
        return new self(sprintf(
            'Order %s berstatus %s, sehingga produksi belum dapat dimulai.',
            $order->order_number,
            $order->status->getLabel(),
        ));
    }
}
