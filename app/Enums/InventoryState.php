<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Keadaan fisik item di gudang. Setiap keadaan tetap wajib terikat Tag ID Klien
 * dan ID Pesanan (FR-005).
 */
enum InventoryState: string implements HasColor, HasLabel
{
    case RAW = 'raw';
    case WIP = 'wip';
    case FINISHED = 'finished';

    public function getLabel(): string
    {
        return match ($this) {
            self::RAW => 'Bahan Mentah',
            self::WIP => 'WIP (Setengah Jadi)',
            self::FINISHED => 'Barang Jadi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::RAW => 'info',
            self::WIP => 'warning',
            self::FINISHED => 'success',
        };
    }
}
