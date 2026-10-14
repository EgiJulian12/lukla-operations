<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;

/**
 * Perpindahan status order di luar alur permintaan → penawaran → persetujuan.
 *
 * Dibuat sebagai exception domain agar lapisan UI dapat menampilkannya sebagai
 * galat validasi, bukan galat 500.
 */
class InvalidOrderQuotationState extends RuntimeException
{
    public static function notAwaitingQuotation(Order $order): self
    {
        return new self(sprintf(
            'Order %s berstatus "%s" — penawaran hanya dapat diberikan pada permintaan klien yang belum ditawar.',
            $order->order_number,
            $order->status->value,
        ));
    }

    public static function notAwaitingClientApproval(Order $order): self
    {
        return new self(sprintf(
            'Order %s berstatus "%s" — belum ada penawaran yang menunggu persetujuan klien.',
            $order->order_number,
            $order->status->value,
        ));
    }

    public static function notQuoteApproved(Order $order): self
    {
        return new self(sprintf(
            'Order %s berstatus "%s" — permintaan sample hanya dapat dikirim setelah penawaran disetujui klien.',
            $order->order_number,
            $order->status->value,
        ));
    }

    public static function sampleAlreadyRequested(Order $order): self
    {
        return new self(sprintf(
            'Order %s sudah memiliki permintaan sample.',
            $order->order_number,
        ));
    }
}
