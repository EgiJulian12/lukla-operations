<?php

namespace App\Exceptions;

use App\Models\Sample;
use App\Models\SampleRevision;
use RuntimeException;

/**
 * Perpindahan status sample yang tidak sah (FR-003, FR-004).
 *
 * Dibuat sebagai exception domain agar lapisan UI dapat menampilkannya sebagai
 * galat validasi, bukan galat 500.
 */
class InvalidSampleState extends RuntimeException
{
    public static function notApprovable(SampleRevision $revision): self
    {
        return new self(sprintf(
            'Revisi v%d dari sample #%d tidak sedang menunggu persetujuan.',
            $revision->version,
            $revision->sample_id,
        ));
    }

    public static function notReadyForOrder(Sample $sample): self
    {
        return new self(sprintf(
            'Sample #%d belum memiliki revisi yang disetujui, sehingga belum dapat dikonversi menjadi order massal.',
            $sample->getKey(),
        ));
    }
}
