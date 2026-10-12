<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Jenis tarif pada rate card (FR-010): ongkos potong dan ongkos jahit.
 */
enum RateType: string implements HasLabel
{
    case CUTTING = 'cutting';
    case SEWING = 'sewing';

    public function getLabel(): string
    {
        return match ($this) {
            self::CUTTING => 'Ongkos Potong (Cutting)',
            self::SEWING => 'Ongkos Jahit (Sewing)',
        };
    }
}
