<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Margin rate card tidak memenuhi syarat validasi (Req 2.2-2.7).
 *
 * Margin tersedia harus positif, dan rate komponen tidak boleh melebihi
 * margin tersedia, baik secara individual maupun total.
 */
class InvalidRateMarginException extends RuntimeException
{
    /**
     * Margin tersedia <= 0 setelah dikurangi komponen biaya (Req 2.2, 2.3).
     */
    public static function nonPositive(string $message): self
    {
        return new self($message);
    }

    /**
     * Rate satu komponen melebihi margin tersedia (Req 2.4, 2.5).
     */
    public static function exceedsLimit(float $componentRate, float $availableMargin): self
    {
        return new self(sprintf(
            'Rate komponen Rp %s melebihi batas maksimum yang diizinkan (Rp %s).',
            number_format($componentRate, 2),
            number_format($availableMargin, 2),
        ));
    }

    /**
     * Total rate semua komponen melebihi margin tersedia (Req 2.6, 2.7).
     */
    public static function exceedsTotal(float $totalRates, float $availableMargin, float $shortfall): self
    {
        return new self(sprintf(
            'Total rate komponen Rp %s melebihi margin tersedia Rp %s (kekurangan: Rp %s).',
            number_format($totalRates, 2),
            number_format($availableMargin, 2),
            number_format($shortfall, 2),
        ));
    }
}
