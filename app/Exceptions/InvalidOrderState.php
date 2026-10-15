<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
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

    public static function cannotTransition(Order $order, OrderStatus $target): self
    {
        return new self(sprintf(
            'Order %s tidak dapat berpindah status dari %s ke %s.',
            $order->order_number,
            $order->status->getLabel(),
            $target->getLabel(),
        ));
    }

    public static function notEligibleForClosure(Order $order): self
    {
        return new self(sprintf(
            'Order %s belum memenuhi syarat untuk ditutup (harus berstatus Selesai dan seluruh tagihan telah lunas).',
            $order->order_number,
        ));
    }

    public static function notInProduction(Order $order): self
    {
        return new self(sprintf(
            'Order %s berstatus %s, penugasan penjahit hanya dapat diubah saat order sedang Dalam Produksi.',
            $order->order_number,
            $order->status->getLabel(),
        ));
    }
}
