<?php

namespace App\Exceptions;

use App\Enums\QcResult;
use RuntimeException;

/**
 * Inspeksi QC tidak lengkap (FR-014).
 */
class InvalidQcInspection extends RuntimeException
{
    public static function missingDefectCategory(QcResult $result): self
    {
        return new self(sprintf(
            'Kategori cacat wajib dipilih untuk hasil %s.',
            $result->getLabel(),
        ));
    }
}
