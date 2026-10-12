<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Hasil inspeksi QC (FR-014). Hanya produk dengan hasil LOLOS yang masuk
 * pool bonus QC (FR-017).
 */
enum QcResult: string implements HasColor, HasLabel
{
    case PASS = 'pass';
    case REPAIR = 'repair';
    case SCRAP = 'scrap';

    public function getLabel(): string
    {
        return match ($this) {
            self::PASS => 'Lolos',
            self::REPAIR => 'Repair',
            self::SCRAP => 'Scrap / Defect',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PASS => 'success',
            self::REPAIR => 'warning',
            self::SCRAP => 'danger',
        };
    }

    /**
     * Hanya unit yang lolos QC yang membentuk pool bonus (FR-017).
     */
    public function countsTowardBonusPool(): bool
    {
        return $this === self::PASS;
    }

    /**
     * Kategori cacat wajib diisi untuk hasil selain lolos (FR-014).
     */
    public function requiresDefectCategory(): bool
    {
        return $this !== self::PASS;
    }
}
