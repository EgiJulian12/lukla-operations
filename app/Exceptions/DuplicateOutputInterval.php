<?php

namespace App\Exceptions;

use App\Models\OutputEntry;
use RuntimeException;

/**
 * Satu penjahit sudah punya catatan output untuk komponen dan interval yang
 * sama (FR-011).
 *
 * Interval 30 menit bersifat tunggal per penjahit dan komponen; koreksi
 * dilakukan dengan mengubah catatan yang ada atau menolaknya, bukan dengan
 * menumpuk baris kedua.
 */
class DuplicateOutputInterval extends RuntimeException
{
    public function __construct(
        public readonly int $userId,
        public readonly int $componentId,
        public readonly string $intervalStart,
    ) {
        parent::__construct(sprintf(
            'Penjahit #%d sudah melaporkan output komponen #%d untuk interval %s.',
            $userId,
            $componentId,
            $intervalStart,
        ));
    }

    public static function for(OutputEntry $existing): self
    {
        return new self(
            (int) $existing->user_id,
            (int) $existing->component_id,
            $existing->interval_start->format('d/m/Y H:i'),
        );
    }
}
