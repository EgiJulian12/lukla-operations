<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipe atau kategori item dalam pemenuhan pesanan.
 */
enum MaterialItemType: string implements HasColor, HasLabel
{
    case FABRIC = 'bahan_utama';
    case ACCESSORY = 'aksesoris';
    case TRIM = 'bahan_pendukung';
    case PACKAGING = 'kemasan';
    case OTHER = 'lainnya';

    public function getLabel(): string
    {
        return match ($this) {
            self::FABRIC => 'Bahan Utama / Kain',
            self::ACCESSORY => 'Aksesoris',
            self::TRIM => 'Bahan Pendukung / Trim',
            self::PACKAGING => 'Kemasan / Packaging',
            self::OTHER => 'Lainnya',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::FABRIC => 'primary',
            self::ACCESSORY => 'warning',
            self::TRIM => 'info',
            self::PACKAGING => 'gray',
            self::OTHER => 'gray',
        };
    }
}
