<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Skema kerja sama maklon — menentukan siapa yang menyediakan bahan baku.
 *
 * @see docs/lukla-operations-engine.md — Konteks Bisnis & Skema Maklon
 */
enum WorkScheme: string implements HasLabel
{
    case CMT = 'cmt';
    case SEMI_CMT = 'semi_cmt';
    case FULL_PACKAGE = 'full_package';

    public function getLabel(): string
    {
        return match ($this) {
            self::CMT => 'CMT (Cut, Make, Trim)',
            self::SEMI_CMT => 'Semi-CMT',
            self::FULL_PACKAGE => 'CMT + Bahan (Full Package)',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CMT => 'Klien menyediakan seluruh bahan baku. Lukla menyediakan jasa cutting, jahit, dan finishing.',
            self::SEMI_CMT => 'Klien menyediakan bahan utama. Lukla menyediakan bahan pendukung dan aksesoris.',
            self::FULL_PACKAGE => 'Lukla menyediakan seluruh bahan baku dan jasa produksi.',
        };
    }
}
