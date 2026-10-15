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

    public static function notRejected(OutputEntry $entry): self
    {
        return new self(sprintf(
            'Output #%d berstatus %s, sehingga tidak dapat diajukan ulang.',
            $entry->getKey(),
            $entry->status->getLabel(),
        ));
    }

    public static function selfVerificationForbidden(OutputEntry $entry): self
    {
        return new self(sprintf(
            'Operator dilarang memverifikasi atau menolak output miliknya sendiri (Output #%d).',
            $entry->getKey(),
        ));
    }

    public static function unauthorizedVerifier(): self
    {
        return new self('Hanya Kepala Produksi atau Mandor (PRODUCTION_LEAD) yang berhak memverifikasi atau menolak output.');
    }
}
