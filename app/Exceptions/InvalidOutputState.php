<?php

namespace App\Exceptions;

use App\Models\OutputEntry;
use RuntimeException;

/**
 * Output tidak berada dalam keadaan yang memungkinkan aksi yang diminta
 * (FR-012).
 */
class InvalidOutputState extends RuntimeException
{
    public static function notAwaitingVerification(OutputEntry $entry): self
    {
        return new self(sprintf(
            'Output #%d berstatus %s, sehingga tidak dapat diverifikasi atau ditolak lagi.',
            $entry->getKey(),
            $entry->status->getLabel(),
        ));
    }

    public static function notVerified(OutputEntry $entry): self
    {
        return new self(sprintf(
            'Output #%d belum diverifikasi, sehingga belum dapat dirakit (FR-013).',
            $entry->getKey(),
        ));
    }
}
