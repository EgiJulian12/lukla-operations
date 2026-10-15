<?php

namespace App\Exceptions;

use App\Models\Component;
use App\Models\Order;
use App\Models\ProductionLine;
use App\Models\User;
use RuntimeException;

/**
 * Operator belum ditugaskan ke line pada interval waktu yang diberikan (Req 3.6).
 *
 * Output hanya boleh dicatat oleh operator yang memiliki penugasan aktif
 * di line tersebut pada saat interval berlangsung.
 */
class UnassignedOperatorException extends RuntimeException
{
    public static function forLine(User $operator, ProductionLine $line): self
    {
        return new self(sprintf(
            'Operator #%d (%s) tidak memiliki penugasan aktif di line #%d (%s) pada interval ini.',
            $operator->getKey(),
            $operator->name,
            $line->getKey(),
            $line->name,
        ));
    }

    public static function forComponent(User $operator, Component $component, Order $order): self
    {
        return new self(sprintf(
            'Operator #%d (%s) tidak ditugaskan untuk komponen %s pada order %s.',
            $operator->getKey(),
            $operator->name,
            $component->name,
            $order->order_number,
        ));
    }

    public static function notTailor(User $operator): self
    {
        return new self(sprintf(
            'Pengguna #%d (%s) tidak memiliki peran Penjahit sehingga tidak dapat mencatat output.',
            $operator->getKey(),
            $operator->name,
        ));
    }
}
