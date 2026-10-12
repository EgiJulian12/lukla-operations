<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status periode payroll borongan mingguan (FR-015).
 * Periode yang sudah disetujui terkunci dan tidak dapat dihitung ulang.
 */
enum PayrollPeriodStatus: string implements HasColor, HasLabel
{
    case OPEN = 'open';
    case CALCULATED = 'calculated';
    case APPROVED = 'approved';
    case PAID = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN => 'Terbuka',
            self::CALCULATED => 'Sudah Dihitung',
            self::APPROVED => 'Disetujui',
            self::PAID => 'Sudah Dibayar',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OPEN => 'gray',
            self::CALCULATED => 'warning',
            self::APPROVED => 'info',
            self::PAID => 'success',
        };
    }

    public function isLocked(): bool
    {
        return in_array($this, [self::APPROVED, self::PAID], true);
    }
}
