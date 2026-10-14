<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status alur kerja repair/perbaikan barang (BR 4.6, FR-014).
 */
enum RepairStatus: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case IN_REPAIR = 'in_repair';
    case COMPLETED = 'completed';
    case VERIFIED = 'verified';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Perbaikan',
            self::IN_REPAIR => 'Sedang Diperbaiki',
            self::COMPLETED => 'Siap Dicek Ulang',
            self::VERIFIED => 'Lolos QC Ulang',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::IN_REPAIR => 'info',
            self::COMPLETED => 'primary',
            self::VERIFIED => 'success',
        };
    }
}
