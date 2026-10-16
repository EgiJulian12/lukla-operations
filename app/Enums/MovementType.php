<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Jenis pergerakan stok. Setiap pergerakan wajib membawa client_id dan order_id
 * yang sama dengan item yang dipindahkan (FR-006), dan divalidasi sebelum
 * transaksi tercatat (FR-007, NFR-002).
 */
enum MovementType: string implements HasColor, HasLabel
{
    case RECEIPT = 'receipt';
    case ISSUE_TO_CUTTING = 'issue_to_cutting';
    case TRANSFER = 'transfer';
    case ADJUSTMENT = 'adjustment';
    case FG_IN = 'fg_in';

    public function getLabel(): string
    {
        return match ($this) {
            self::RECEIPT => 'Penerimaan Bahan',
            self::ISSUE_TO_CUTTING => 'Masuk Proses Cutting',
            self::TRANSFER => 'Transfer',
            self::ADJUSTMENT => 'Penyesuaian (Stock Opname)',
            self::FG_IN => 'Masuk Barang Jadi',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::RECEIPT, self::FG_IN => 'success',
            self::ISSUE_TO_CUTTING => 'info',
            self::TRANSFER => 'warning',
            self::ADJUSTMENT => 'gray',
        };
    }

    /**
     * Hanya pergerakan yang mengurangi stok yang perlu divalidasi
     * kecocokan klien/pesanan sebelum dicatat (FR-007).
     */
    public function requiresOwnershipMatch(): bool
    {
        return in_array($this, [self::ISSUE_TO_CUTTING, self::TRANSFER], true);
    }
}
