<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Pihak yang bertanggung jawab menyediakan bahan/komponen/aksesoris.
 */
enum MaterialResponsibility: string implements HasColor, HasLabel
{
    case CLIENT = 'klien';
    case COMPANY = 'perusahaan';
    case PARTIAL = 'sebagian';

    public function getLabel(): string
    {
        return match ($this) {
            self::CLIENT => 'Ditanggung Klien',
            self::COMPANY => 'Ditanggung Perusahaan (Lukla)',
            self::PARTIAL => 'Ditanggung Sebagian (Klien & Lukla)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::CLIENT => 'info',
            self::COMPANY => 'purple',
            self::PARTIAL => 'amber',
        };
    }
}
