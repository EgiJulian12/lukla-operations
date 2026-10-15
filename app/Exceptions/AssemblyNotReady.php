<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;

/**
 * Order belum memenuhi syarat perakitan (FR-012 → FR-013).
 *
 * Perakitan hanya boleh menyatukan pekerjaan yang sudah lolos verifikasi mandor,
 * jadi ada dua hal yang menahannya: output yang masih menunggu verifikasi, dan
 * komponen artikel yang belum punya output terverifikasi sama sekali.
 */
class AssemblyNotReady extends RuntimeException
{
    /**
     * @param  array<int, string>  $componentNames
     */
    public static function missingVerifiedComponents(Order $order, array $componentNames): self
    {
        return new self(sprintf(
            'Order %s belum dapat dirakit: komponen berikut belum punya output terverifikasi — %s.',
            $order->order_number,
            implode(', ', $componentNames),
        ));
    }

    public static function awaitingVerification(Order $order, int $pendingCount): self
    {
        return new self(sprintf(
            'Order %s belum dapat dirakit: masih ada %d output yang menunggu verifikasi mandor.',
            $order->order_number,
            $pendingCount,
        ));
    }

    public static function noVerifiedOutput(Order $order): self
    {
        return new self(sprintf(
            'Order %s belum dapat dirakit: belum ada satu pun output yang terverifikasi.',
            $order->order_number,
        ));
    }
}
