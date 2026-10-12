<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status invoice klien — draf, terbit, lalu lunas (matriks RBAC SRS, Finance).
 */
enum InvoiceStatus: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case ISSUED = 'issued';
    case PAID = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::ISSUED => 'Terbit',
            self::PAID => 'Lunas',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ISSUED => 'warning',
            self::PAID => 'success',
        };
    }

    /**
     * Invoice yang sudah terbit tidak disunting lagi; koreksi memakai dokumen
     * baru sehingga nilai yang ditagih tetap dapat diaudit (NFR-005).
     */
    public function isLocked(): bool
    {
        return $this !== self::DRAFT;
    }
}
