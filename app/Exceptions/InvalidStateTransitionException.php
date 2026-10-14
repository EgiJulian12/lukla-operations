<?php

namespace App\Exceptions;

use App\Enums\InventoryState;
use RuntimeException;

/**
 * Dilempar saat transisi keadaan fisik item stok melanggar urutan yang sah (FR-012).
 * Urutan yang sah adalah: RAW -> WIP -> FINISHED.
 */
class InvalidStateTransitionException extends RuntimeException
{
    public static function for(InventoryState $current, InventoryState $target): self
    {
        return new self(sprintf(
            'Transisi status tidak valid dari [%s] ke [%s]. Urutan yang sah: RAW -> WIP -> FINISHED.',
            $current->getLabel(),
            $target->getLabel(),
        ));
    }
}
