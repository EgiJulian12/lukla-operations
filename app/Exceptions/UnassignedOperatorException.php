<?php

namespace App\Exceptions;

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
}
