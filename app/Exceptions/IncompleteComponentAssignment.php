<?php

namespace App\Exceptions;

use App\Models\Order;
use RuntimeException;

/**
 * Rencana penjahit belum menutup seluruh komponen artikel, sehingga order tidak
 * boleh mulai berjalan tanpa pemilik komponen yang jelas (FR-009).
 */
class IncompleteComponentAssignment extends RuntimeException
{
    /**
     * @param  array<int, string>  $missingComponents
     */
    public static function missingComponents(Order $order, array $missingComponents): self
    {
        return new self(sprintf(
            'Order %s belum menugaskan penjahit untuk komponen: %s.',
            $order->order_number,
            implode(', ', $missingComponents),
        ));
    }

    public static function duplicatedComponent(Order $order): self
    {
        return new self(sprintf(
            'Rencana penjahit order %s memuat komponen yang sama lebih dari sekali.',
            $order->order_number,
        ));
    }
}
