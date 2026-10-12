<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status order produksi massal, dari pembuatan hingga selesai.
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    case DRAFT = 'draft';
    case CONFIRMED = 'confirmed';
    case IN_PRODUCTION = 'in_production';
    case IN_QC = 'in_qc';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::CONFIRMED => 'Dikonfirmasi',
            self::IN_PRODUCTION => 'Dalam Produksi',
            self::IN_QC => 'Dalam QC',
            self::COMPLETED => 'Selesai',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::CONFIRMED => 'info',
            self::IN_PRODUCTION => 'warning',
            self::IN_QC => 'primary',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }

    /**
     * Order yang sudah dibatalkan atau selesai tidak boleh diubah lagi —
     * mendukung aturan "locked transaction history" pada matriks RBAC.
     */
    public function isLocked(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED], true);
    }
}
